#!/usr/bin/env python3
"""
Porównywarka cen WWW (za osobę) — czyta to, co widać na stronie oferty.

Tryb pojedynczy URL — bezpośrednie adresy stron ofert:
  python scripts/compare_www_prices.py --file urls-pages.txt

Tryb całej oferty (--catalog) — miejsce po miejscu, impreza po imprezie:
  python scripts/compare_www_prices.py --catalog --file urls.txt

  urls.txt (same base URL środowisk):
    local|http://127.0.0.1:8000
    prod|https://bprafa.pl
    dev|https://sor41.webgarage.pl

Wymaga: pip install -r scripts/requirements-www-price-compare.txt
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
import time
import warnings
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass, field
from typing import Iterable
from urllib.parse import urljoin, urlparse

warnings.filterwarnings("ignore", message="urllib3 v2 only supports OpenSSL")

try:
    import requests
    from bs4 import BeautifulSoup
except ImportError as exc:  # pragma: no cover
    print(
        "Brak zależności. Zainstaluj:\n"
        "  pip install -r scripts/requirements-www-price-compare.txt",
        file=sys.stderr,
    )
    raise SystemExit(2) from exc


PACKAGE_URL_RE = re.compile(
    r"/(?P<region>[A-Za-z0-9\-]+)/(?P<days>\d+-dniowe)/(?P<id>\d+)/(?P<slug>[^/?#]+)",
    re.UNICODE,
)
TIER_TEXT_RE = re.compile(
    r"za\s+osob[eę]\s+dla\s+grupy\s+(?P<from>\d+)\s*[–\-]\s*(?P<to>\d+)\s*os[oó]b",
    re.IGNORECASE | re.UNICODE,
)
PLN_RE = re.compile(r"([\d\s]+)\s*PLN", re.IGNORECASE)

HTTP_HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (compatible; SorSystem-WWW-Price-Compare/1.0; +internal QA)"
    ),
    "Accept-Language": "pl-PL,pl;q=0.9",
}
JSON_HEADERS = {
    **HTTP_HEADERS,
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest",
}


@dataclass(frozen=True)
class PriceTier:
    qty_from: int
    qty_to: int
    display: str
    pln: int | None

    @property
    def key(self) -> tuple[int, int]:
        return (self.qty_from, self.qty_to)


@dataclass
class PagePrices:
    label: str
    url: str
    template_id: int | None
    slug: str | None
    region_slug: str | None
    event_name: str
    city: str
    tiers: list[PriceTier] = field(default_factory=list)
    error: str | None = None

    @property
    def match_key(self) -> tuple[int | None, str]:
        city_norm = normalize_city(self.city)
        return (self.template_id, city_norm)


@dataclass(frozen=True)
class StartPlace:
    place_id: str
    region_slug: str
    name: str


@dataclass
class CompareResult:
    status: str  # ok | price_diff | missing | fetch_error | no_catalog
    max_delta: float
    event_name: str
    details: str
    min_prices: dict[str, int | None] = field(default_factory=dict)


@dataclass(frozen=True)
class PackageRef:
    template_id: int
    slug: str
    days: str
    region_slug: str
    city_name: str

    def url_for_base(self, base: str) -> str:
        return f"{normalize_base(base)}/{self.region_slug}/{self.days}/{self.template_id}/{self.slug}"

    @property
    def match_key(self) -> tuple[int, str]:
        return (self.template_id, normalize_city(self.city_name))


def normalize_city(name: str) -> str:
    return re.sub(r"\s+", " ", name.strip().lower())


def normalize_base(url: str) -> str:
    return url.rstrip("/")


def is_base_url(url: str) -> bool:
    path = urlparse(url).path.rstrip("/")
    return path == ""


def parse_template_from_url(url: str) -> tuple[int | None, str | None, str | None]:
    path = urlparse(url).path
    match = PACKAGE_URL_RE.search(path)
    if not match:
        return None, None, None
    return int(match.group("id")), match.group("slug"), match.group("region")


def parse_pln(display: str) -> int | None:
    match = PLN_RE.search(display.replace("\xa0", " "))
    if not match:
        return None
    digits = re.sub(r"\s+", "", match.group(1))
    try:
        return int(digits)
    except ValueError:
        return None


def make_session() -> requests.Session:
    session = requests.Session()
    session.headers.update(HTTP_HEADERS)
    return session


def fetch_html(
    session: requests.Session,
    url: str,
    timeout: float,
) -> tuple[str, str]:
    response = session.get(url, timeout=timeout, allow_redirects=True)
    response.raise_for_status()
    response.encoding = response.apparent_encoding or "utf-8"
    return response.text, response.url


def is_listing_redirect(original_url: str, final_url: str) -> bool:
    final_path = urlparse(final_url).path.rstrip("/")
    if final_path.endswith("/oferty"):
        return True

    expected_id = parse_template_from_url(original_url)[0]
    final_id = parse_template_from_url(final_url)[0]
    return expected_id is not None and final_id != expected_id


def extract_event_name(soup: BeautifulSoup) -> str:
    for selector in ("h1.title", ".title-section .title", ".title-section h1", "h1"):
        element = soup.select_one(selector)
        if element:
            text = element.get_text(strip=True)
            if text:
                return text
    return "—"


def parse_page(html: str, url: str, label: str, final_url: str | None = None) -> PagePrices:
    template_id, slug, region_slug = parse_template_from_url(url)
    resolved_url = final_url or url
    soup = BeautifulSoup(html, "html.parser")

    event_name = extract_event_name(soup)

    city_el = soup.select_one(".current-region-name")
    city = city_el.get_text(strip=True) if city_el else (region_slug or "—")

    tiers: list[PriceTier] = []
    price_root = soup.select_one("#price-scroll") or soup.select_one(".content.price")
    scope = price_root if price_root else soup
    for block in scope.select(".people_price"):
        small1 = block.select_one(".small1")
        price_el = block.select_one(".price")
        if not small1 or not price_el:
            continue

        tier_text = small1.get_text(" ", strip=True)
        if "zapytaj o ofertę" in tier_text.lower():
            continue

        match = TIER_TEXT_RE.search(tier_text)
        if not match:
            continue

        display = price_el.get_text(" ", strip=True)
        if not display or display.lower().startswith("zapytaj"):
            continue

        tiers.append(
            PriceTier(
                qty_from=int(match.group("from")),
                qty_to=int(match.group("to")),
                display=display,
                pln=parse_pln(display),
            )
        )

    page = PagePrices(
        label=label,
        url=resolved_url,
        template_id=template_id,
        slug=slug,
        region_slug=region_slug,
        event_name=event_name,
        city=city,
        tiers=tiers,
    )

    if is_listing_redirect(url, resolved_url):
        page.error = (
            "Przekierowanie na listę ofert (/oferty) — impreza niedostępna "
            "dla miasta z URL."
        )
        return page

    if not tiers:
        page.error = "Nie znaleziono wierszy cennika (people_price w sekcji Cennik)."

    return page


def discover_start_places(session: requests.Session, base: str, timeout: float) -> list[StartPlace]:
    listing_url = f"{normalize_base(base)}/warszawa/oferty"
    html, _ = fetch_html(session, listing_url, timeout)
    soup = BeautifulSoup(html, "html.parser")

    places: list[StartPlace] = []
    seen: set[str] = set()
    for option in soup.select('select[name="start_place_id"] option'):
        place_id = (option.get("value") or "").strip()
        if not place_id or place_id in seen:
            continue
        seen.add(place_id)
        region_slug = (option.get("data-slug") or "").strip()
        name = option.get_text(strip=True)
        if not region_slug:
            region_slug = re.sub(r"\s+i okolice$", "", name, flags=re.I)
            region_slug = region_slug.lower().replace(" ", "-")
        places.append(
            StartPlace(
                place_id=place_id,
                region_slug=region_slug,
                name=re.sub(r"\s+i okolice$", "", name, flags=re.I),
            )
        )

    return places


def extract_packages_from_html(html: str, place: StartPlace) -> list[PackageRef]:
    soup = BeautifulSoup(html, "html.parser")
    found: dict[int, PackageRef] = {}

    for anchor in soup.find_all("a", href=True):
        match = PACKAGE_URL_RE.search(anchor["href"])
        if not match:
            continue
        template_id = int(match.group("id"))
        if template_id in found:
            continue
        found[template_id] = PackageRef(
            template_id=template_id,
            slug=match.group("slug"),
            days=match.group("days"),
            region_slug=place.region_slug,
            city_name=place.name,
        )

    return sorted(found.values(), key=lambda item: item.template_id)


def discover_packages_for_place(
    session: requests.Session,
    base: str,
    place: StartPlace,
    timeout: float,
) -> list[PackageRef]:
    base = normalize_base(base)
    packages: dict[int, PackageRef] = {}

    listing_url = f"{base}/{place.region_slug}/oferty"
    html, _ = fetch_html(session, listing_url, timeout)
    for package in extract_packages_from_html(html, place):
        packages[package.template_id] = package

    page = 1
    while page:
        response = session.get(
            listing_url,
            params={"page": page, "partial": "1"},
            headers=JSON_HEADERS,
            timeout=timeout,
        )
        response.raise_for_status()
        data = response.json()
        for package in extract_packages_from_html(data.get("html", ""), place):
            packages[package.template_id] = package
        page = data.get("next_page")

    return sorted(packages.values(), key=lambda item: item.template_id)


def resolve_input_path(path: str) -> str:
    from pathlib import Path

    candidate = Path(path)
    if candidate.is_file():
        return str(candidate)

    script_dir = Path(__file__).resolve().parent
    from_script = script_dir / path
    if from_script.is_file():
        return str(from_script)

    if candidate.name == path and (script_dir / "urls.txt").is_file():
        return str(script_dir / "urls.txt")

    hints = [
        str(script_dir / "urls.txt"),
        str(script_dir / "urls.example.txt"),
    ]
    raise FileNotFoundError(
        f"Nie ma pliku: {path}\n"
        f"Utwórz go (np. skopiuj scripts/urls.example.txt) albo podaj pełną ścieżkę:\n"
        f"  python3 scripts/compare_www_prices.py --catalog --file scripts/urls.txt\n"
        f"Sugerowane lokalizacje:\n"
        + "\n".join(f"  - {hint}" for hint in hints)
    )


def load_url_specs(args: argparse.Namespace) -> list[tuple[str, str]]:
    specs: list[tuple[str, str]] = []

    if args.file:
        file_path = resolve_input_path(args.file)
        with open(file_path, encoding="utf-8") as handle:
            for raw in handle:
                line = raw.strip()
                if not line or line.startswith("#"):
                    continue
                if "|" in line:
                    label, url = line.split("|", 1)
                    specs.append((label.strip(), url.strip()))
                else:
                    specs.append((f"url{len(specs) + 1}", line))

    for label, url in args.labeled_urls:
        specs.append((label, url))

    for url in args.urls:
        specs.append((f"url{len(specs) + 1}", url))

    return specs


def group_pages(pages: list[PagePrices]) -> dict[tuple[int | None, str], list[PagePrices]]:
    groups: dict[tuple[int | None, str], list[PagePrices]] = {}
    for page in pages:
        groups.setdefault(page.match_key, []).append(page)
    return groups


def format_price(value: int | None) -> str:
    if value is None:
        return "—"
    return f"{value:,}".replace(",", " ") + " zł"


def compare_group(
    pages: list[PagePrices],
    threshold: float,
    only_diffs: bool,
    *,
    compact: bool = False,
) -> list[str]:
    lines: list[str] = []
    reference = pages[0]

    all_tier_keys: set[tuple[int, int]] = set()
    tiers_by_label: dict[str, dict[tuple[int, int], PriceTier]] = {}

    for page in pages:
        tiers_by_label[page.label] = {tier.key: tier for tier in page.tiers}
        all_tier_keys.update(tiers_by_label[page.label].keys())

    if not all_tier_keys:
        if not only_diffs or any(page.error for page in pages):
            if compact:
                return [
                    f"  [BŁĄD] #{reference.template_id or '?'} {reference.event_name} — brak cennika"
                ]
            header = (
                f"\n{'=' * 72}\n"
                f"Impreza #{reference.template_id or '?'} — {reference.event_name}\n"
                f"Miasto wyjazdu: {reference.city}\n"
                f"{'=' * 72}"
            )
            lines.append(header)
            lines.append("  ⚠ Brak wierszy cennika na żadnej ze stron.")
        return lines

    table_rows: list[str] = []
    diff_count = 0
    match_count = 0
    has_any_diff = False

    for qty_from, qty_to in sorted(all_tier_keys):
        row_prices: dict[str, PriceTier | None] = {
            page.label: tiers_by_label[page.label].get((qty_from, qty_to))
            for page in pages
        }

        pln_values = {
            label: tier.pln
            for label, tier in row_prices.items()
            if tier is not None and tier.pln is not None
        }

        missing = [label for label, tier in row_prices.items() if tier is None]
        has_missing = len(missing) > 0

        max_diff = 0.0
        if len(pln_values) >= 2:
            vals = list(pln_values.values())
            max_diff = float(max(vals) - min(vals))

        is_diff = has_missing or max_diff > threshold
        if is_diff:
            has_any_diff = True

        if only_diffs and not is_diff:
            match_count += 1
            continue

        if is_diff:
            diff_count += 1
            status = "RÓŻNICA"
        else:
            match_count += 1
            status = "OK"

        parts = [f"  [{status}] Grupa {qty_from}–{qty_to} os."]
        for page in pages:
            tier = row_prices[page.label]
            if tier is None:
                parts.append(f"{page.label}: brak")
            else:
                pln_part = format_price(tier.pln) if tier.pln is not None else tier.display
                parts.append(f"{page.label}: {pln_part}")

        if len(pln_values) >= 2 and max_diff > 0:
            parts.append(f"Δ max = {format_price(int(max_diff))}")

        if missing:
            parts.append(f"brak w: {', '.join(missing)}")

        table_rows.append(" | ".join(parts))

    if only_diffs and not has_any_diff:
        return []

    if compact:
        if has_any_diff:
            max_tier_diff = 0
            for qty_from, qty_to in all_tier_keys:
                row_prices = {
                    page.label: tiers_by_label[page.label].get((qty_from, qty_to))
                    for page in pages
                }
                pln_values = [
                    tier.pln
                    for tier in row_prices.values()
                    if tier is not None and tier.pln is not None
                ]
                if len(pln_values) >= 2:
                    max_tier_diff = max(max_tier_diff, max(pln_values) - min(pln_values))
            return [
                f"  [RÓŻNICA] #{reference.template_id} {reference.event_name} "
                f"(max Δ {format_price(int(max_tier_diff))})"
            ]
        if not only_diffs:
            return [f"  [OK] #{reference.template_id} {reference.event_name}"]
        return []

    header = (
        f"\n{'=' * 72}\n"
        f"Impreza #{reference.template_id or '?'} — {reference.event_name}\n"
        f"Miasto wyjazdu: {reference.city}\n"
        f"Źródła: {', '.join(p.label for p in pages)}\n"
        f"{'=' * 72}"
    )
    lines.append(header)
    lines.append(
        f"  Wiersze cennika: {len(all_tier_keys)} | zgodne: {match_count} | różne: {diff_count}"
    )
    lines.extend(table_rows)
    for page in pages:
        if page.tiers:
            lines.append(f"  → [{page.label}] {page.url}")

    return lines


def min_tier_price(page: PagePrices) -> int | None:
    pln_values = [tier.pln for tier in page.tiers if tier.pln is not None]
    return min(pln_values) if pln_values else None


def analyze_pages(pages: list[PagePrices], threshold: float) -> CompareResult:
    reference = pages[0]
    event_name = next((p.event_name for p in pages if p.event_name != "—"), "—")
    min_prices = {page.label: min_tier_price(page) for page in pages}

    fetch_errors = [
        f"{page.label}: {page.error}"
        for page in pages
        if page.error and "Przekierowanie" not in (page.error or "")
    ]
    if fetch_errors:
        return CompareResult(
            status="fetch_error",
            max_delta=0.0,
            event_name=event_name,
            details="; ".join(fetch_errors),
            min_prices=min_prices,
        )

    missing = [
        page.label
        for page in pages
        if page.error and "Przekierowanie" in page.error
    ]
    if missing:
        return CompareResult(
            status="missing",
            max_delta=0.0,
            event_name=event_name,
            details=f"brak oferty na: {', '.join(missing)}",
            min_prices=min_prices,
        )

    ok_pages = [page for page in pages if not page.error and page.tiers]
    if not ok_pages:
        return CompareResult(
            status="no_catalog",
            max_delta=0.0,
            event_name=event_name,
            details="brak sekcji cennika na wszystkich środowiskach",
            min_prices=min_prices,
        )

    lines = compare_group(pages, threshold=threshold, only_diffs=True, compact=True)
    if lines and any(line.startswith("  [RÓŻNICA]") for line in lines):
        max_delta = 0.0
        tiers_by_label: dict[str, dict[tuple[int, int], PriceTier]] = {}
        all_keys: set[tuple[int, int]] = set()
        for page in pages:
            tiers_by_label[page.label] = {tier.key: tier for tier in page.tiers}
            all_keys.update(tiers_by_label[page.label].keys())
        for key in all_keys:
            pln_values = [
                tiers_by_label[label][key].pln
                for label in tiers_by_label
                if key in tiers_by_label[label] and tiers_by_label[label][key].pln is not None
            ]
            if len(pln_values) >= 2:
                max_delta = max(max_delta, float(max(pln_values) - min(pln_values)))

        return CompareResult(
            status="price_diff",
            max_delta=max_delta,
            event_name=event_name,
            details=f"max Δ {format_price(int(max_delta))}",
            min_prices=min_prices,
        )

    return CompareResult(
        status="ok",
        max_delta=0.0,
        event_name=event_name,
        details="",
        min_prices=min_prices,
    )


class CsvResultWriter:
    def __init__(self, path: str, env_labels: list[str]) -> None:
        self.path = path
        price_cols = [f"cena_{label}_pln" for label in env_labels]
        self.fieldnames = [
            "miasto",
            "template_id",
            "nazwa",
            "status",
            "max_delta_pln",
            *price_cols,
            "szczegoly",
        ]
        self._file = open(path, "w", encoding="utf-8-sig", newline="")
        self._writer = csv.DictWriter(self._file, fieldnames=self.fieldnames, delimiter=";")
        self._writer.writeheader()
        self._file.flush()

    def write_row(
        self,
        place_name: str,
        package: PackageRef,
        result: CompareResult,
        env_labels: list[str],
    ) -> None:
        row = {
            "miasto": place_name,
            "template_id": package.template_id,
            "nazwa": result.event_name,
            "status": result.status,
            "max_delta_pln": int(result.max_delta) if result.max_delta else "",
            "szczegoly": result.details,
        }
        for label in env_labels:
            price = result.min_prices.get(label)
            row[f"cena_{label}_pln"] = price if price is not None else ""
        self._writer.writerow(row)
        self._file.flush()

    def close(self) -> None:
        self._file.close()


def fetch_package_pages(
    specs: list[tuple[str, str]],
    package: PackageRef,
    timeout: float,
) -> list[PagePrices]:
    session = make_session()
    pages: list[PagePrices] = []
    for label, base in specs:
        pages.append(
            fetch_page_prices(session, label, package.url_for_base(base), timeout)
        )
    return pages


def fetch_page_prices(
    session: requests.Session,
    label: str,
    url: str,
    timeout: float,
) -> PagePrices:
    try:
        html, final_url = fetch_html(session, url, timeout)
        return parse_page(html, url, label, final_url=final_url)
    except requests.RequestException as exc:
        template_id, slug, region_slug = parse_template_from_url(url)
        page = PagePrices(
            label=label,
            url=url,
            template_id=template_id,
            slug=slug,
            region_slug=region_slug,
            event_name="—",
            city="—",
            error=str(exc),
        )
        return page


def run_catalog_compare(
    specs: list[tuple[str, str]],
    args: argparse.Namespace,
) -> int:
    if len(specs) < 2:
        print("Tryb --catalog wymaga co najmniej 2 środowisk w pliku urls.txt.", file=sys.stderr)
        return 2

    session = make_session()
    catalog_label, catalog_base = specs[0]
    print(f"Katalog imprez ze źródła: [{catalog_label}] {catalog_base}")

    places = discover_start_places(session, catalog_base, args.timeout)
    if args.region:
        wanted = args.region.lower().strip()
        places = [
            place
            for place in places
            if place.region_slug.lower() == wanted
            or normalize_city(place.name) == normalize_city(wanted)
        ]
    if not places:
        print("Nie znaleziono miast wyjazdu.", file=sys.stderr)
        return 1

    env_labels = [label for label, _ in specs]
    est_packages = len(places) * 300
    est_requests = est_packages * len(specs)
    print(
        f"Miasta do sprawdzenia: {len(places)} "
        f"(szac. ~{est_requests:,} requestów HTTP, może trwać wiele godzin)"
    )
    print("Tip: użyj --output wyniki.csv żeby nie stracić postępu po Ctrl+C")
    only_diffs = not args.all
    show_samples = max(0, args.show_samples)

    stats = {
        "places": 0,
        "packages": 0,
        "compared": 0,
        "ok": 0,
        "price_diff": 0,
        "missing": 0,
        "fetch_error": 0,
        "no_catalog": 0,
    }
    diff_lines: list[str] = []
    started = time.time()
    csv_writer: CsvResultWriter | None = None
    if args.output:
        csv_writer = CsvResultWriter(args.output, env_labels)
        print(f"Zapis CSV: {args.output}")

    try:
        for place_index, place in enumerate(places, start=1):
            stats["places"] += 1
            print(f"\n[{place_index}/{len(places)}] {place.name} — pobieram listę imprez…", flush=True)

            try:
                packages = discover_packages_for_place(session, catalog_base, place, args.timeout)
            except requests.RequestException as exc:
                print(f"  BŁĄD listy ofert: {exc}", file=sys.stderr)
                stats["fetch_error"] += 1
                continue

            if args.template_id:
                packages = [pkg for pkg in packages if pkg.template_id == args.template_id]

            if args.max_pairs is not None:
                remaining = args.max_pairs - stats["packages"]
                if remaining <= 0:
                    print("Osiągnięto --max-pairs — przerywam.", flush=True)
                    break
                packages = packages[:remaining]

            stats["packages"] += len(packages)
            print(f"  Imprez w katalogu: {len(packages)}", flush=True)

            place_stats = {key: 0 for key in ("ok", "price_diff", "missing", "fetch_error", "no_catalog")}
            sample_lines: list[str] = []

            workers = max(1, args.workers)
            with ThreadPoolExecutor(max_workers=workers) as executor:
                futures = {
                    executor.submit(fetch_package_pages, specs, package, args.timeout): package
                    for package in packages
                }
                for future in as_completed(futures):
                    package = futures[future]
                    try:
                        pages = future.result()
                    except Exception as exc:  # pragma: no cover
                        pages = []
                        result = CompareResult(
                            status="fetch_error",
                            max_delta=0.0,
                            event_name="—",
                            details=str(exc),
                        )
                    else:
                        result = analyze_pages(pages, args.threshold)

                    stats["compared"] += 1
                    stats[result.status] += 1
                    place_stats[result.status] += 1

                    if csv_writer:
                        csv_writer.write_row(place.name, package, result, env_labels)

                    if result.status == "ok":
                        continue

                    if args.only_price and result.status != "price_diff":
                        continue

                    line = (
                        f"  [{result.status.upper()}] #{package.template_id} "
                        f"{result.event_name} — {result.details}"
                    )
                    diff_lines.append(line)
                    if len(sample_lines) < show_samples:
                        sample_lines.append(line)

            print(
                f"  OK {place_stats['ok']} | "
                f"cena {place_stats['price_diff']} | "
                f"brak na serwerze {place_stats['missing']} | "
                f"błąd {place_stats['fetch_error'] + place_stats['no_catalog']}",
                flush=True,
            )
            if sample_lines:
                print("  Przykłady:", flush=True)
                print("\n".join(sample_lines), flush=True)

    finally:
        if csv_writer:
            csv_writer.close()

    elapsed = time.time() - started
    print(
        f"\n{'=' * 72}\n"
        f"PODSUMOWANIE CAŁEJ OFERTY\n"
        f"  Miasta:              {stats['places']}\n"
        f"  Imprezy w katalogu:  {stats['packages']}\n"
        f"  Porównań:            {stats['compared']}\n"
        f"  Zgodne (cena):       {stats['ok']}\n"
        f"  Różnica ceny:        {stats['price_diff']}\n"
        f"  Brak na serwerze:    {stats['missing']}\n"
        f"  Błąd pobrania:       {stats['fetch_error']}\n"
        f"  Brak cennika:        {stats['no_catalog']}\n"
        f"  Czas:                {elapsed:.0f}s ({elapsed / 60:.1f} min)\n"
        f"{'=' * 72}"
    )
    if args.output:
        print(f"\nPełna lista zapisana w: {args.output}")

    if diff_lines and not args.output:
        print(f"\nWyniki ({len(diff_lines)} wierszy, pierwsze 50):")
        print("\n".join(diff_lines[:50]))
        if len(diff_lines) > 50:
            print(f"  … i {len(diff_lines) - 50} więcej (użyj --output wyniki.csv)")
    elif stats["price_diff"] == 0 and only_diffs and not args.only_price:
        print("\n✓ Brak różnic cenowych powyżej progu.")
    elif args.only_price and stats["price_diff"] == 0:
        print("\n✓ Brak różnic cenowych (pominięto braki na serwerze).")

    has_problems = stats["price_diff"] > 0
    if not args.only_price:
        has_problems = has_problems or stats["missing"] or stats["fetch_error"] or stats["no_catalog"]
    return 1 if has_problems else 0


def print_summary(pages: list[PagePrices], groups: dict[tuple[int | None, str], list[PagePrices]]) -> None:
    print("\nPodsumowanie pobrania:")
    for page in pages:
        status = "OK" if not page.error else f"BŁĄD: {page.error}"
        tier_count = len(page.tiers)
        print(
            f"  [{page.label}] #{page.template_id or '?'} | {page.city} | "
            f"{tier_count} progów | {status}"
        )
        if page.error:
            print(f"           {page.url}")

    multi = [g for g in groups.values() if len(g) > 1]
    single = [g for g in groups.values() if len(g) == 1]
    if single and len(groups) > 1:
        print(f"\n  Uwaga: {len(single)} stron nie ma pary do porównania (inna impreza/miasto).")
    if not multi:
        print("\n  Brak grup z co najmniej 2 URL-ami do porównania — podaj te same imprezy/miasta.")


def run_single_compare(specs: list[tuple[str, str]], args: argparse.Namespace) -> int:
    session = make_session()
    pages: list[PagePrices] = []
    for label, url in specs:
        pages.append(fetch_page_prices(session, label, url, args.timeout))

    groups = group_pages([p for p in pages if p.error is None])
    print_summary(pages, groups)

    only_diffs = not args.all
    output_lines: list[str] = []

    for group_pages_list in groups.values():
        if len(group_pages_list) < 2:
            continue
        output_lines.extend(
            compare_group(group_pages_list, threshold=args.threshold, only_diffs=only_diffs)
        )

    if output_lines:
        print("\n".join(output_lines))
    elif any(p.error is None for p in pages):
        if only_diffs:
            print("\n✓ Brak różnic powyżej progu — cenniki zgodne.")
        else:
            print("\nBrak wyników do wyświetlenia.")

    failed = [p for p in pages if p.error]
    if failed:
        print("\nBłędy pobierania:", file=sys.stderr)
        for page in failed:
            print(f"  [{page.label}] {page.error}\n    {page.url}", file=sys.stderr)
        return 1

    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Porównuje ceny za osobę ze stron WWW ofert (sekcja Cennik).",
    )
    parser.add_argument(
        "urls",
        nargs="*",
        help="Adresy stron ofert (bez etykiety — url1, url2, …)",
    )
    parser.add_argument(
        "--label",
        action="append",
        nargs=2,
        metavar=("NAZWA", "URL"),
        dest="labeled_urls",
        default=[],
        help="Para: etykieta środowiska i URL",
    )
    parser.add_argument(
        "--file",
        "-f",
        help="Plik z URL-ami (format: etykieta|url)",
    )
    parser.add_argument(
        "--catalog",
        action="store_true",
        help="Porównaj całą ofertę: każde miasto, każda impreza (base URL w pliku)",
    )
    parser.add_argument(
        "--region",
        help="Tylko jedno miasto (slug, np. warszawa)",
    )
    parser.add_argument(
        "--template-id",
        type=int,
        help="Tylko jeden szablon imprezy (ID)",
    )
    parser.add_argument(
        "--max-pairs",
        type=int,
        help="Limit liczby par impreza×miasto (do testów)",
    )
    parser.add_argument(
        "--threshold",
        "-t",
        type=float,
        default=1.0,
        help="Próg różnicy PLN (domyślnie 1)",
    )
    parser.add_argument(
        "--all",
        action="store_true",
        help="Pokaż też zgodne wiersze (domyślnie tylko różnice)",
    )
    parser.add_argument(
        "--timeout",
        type=float,
        default=30.0,
        help="Timeout HTTP w sekundach",
    )
    parser.add_argument(
        "--output",
        "-o",
        help="Zapisz każdy wynik do CSV (odporny na Ctrl+C)",
    )
    parser.add_argument(
        "--workers",
        "-w",
        type=int,
        default=4,
        help="Równoległe porównania imprez (domyślnie 4)",
    )
    parser.add_argument(
        "--show-samples",
        type=int,
        default=3,
        help="Ile przykładów problemów wypisać per miasto (domyślnie 3)",
    )
    parser.add_argument(
        "--only-price",
        action="store_true",
        help="Ignoruj brak oferty na serwerze — raportuj tylko różnice cen",
    )
    return parser


def main(argv: Iterable[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(list(argv) if argv is not None else None)

    try:
        specs = load_url_specs(args)
    except FileNotFoundError as exc:
        print(str(exc), file=sys.stderr)
        return 2

    if len(specs) < 1:
        parser.error("Podaj co najmniej jeden URL (--label, --file lub pozycyjnie).")

    catalog_mode = args.catalog or all(is_base_url(url) for _, url in specs)
    if catalog_mode:
        if not all(is_base_url(url) for _, url in specs):
            print(
                "Tryb katalogu wymaga base URL (bez ścieżki do imprezy) w każdej linii.",
                file=sys.stderr,
            )
            return 2
        return run_catalog_compare(specs, args)

    if len(specs) < 2:
        print("Uwaga: podano 1 URL — pobiorę dane, ale nie ma czego porównać.", file=sys.stderr)

    return run_single_compare(specs, args)


if __name__ == "__main__":
    raise SystemExit(main())
