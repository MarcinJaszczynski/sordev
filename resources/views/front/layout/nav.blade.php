<div class="navbar-area" id="stickymenu">
    <!-- Mobile fixed bar: logo left, hamburger right -->
    <div class="mobile-bar d-lg-none">
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('uploads/logo.png') }}" alt="Biuro Podróży RAFA – wycieczki szkolne">
        </a>
        <button class="mobile-toggle" aria-controls="mobileMenuContainer" aria-expanded="false" type="button">
            <span class="hamburger" aria-hidden="true"></span>
            <span class="sr-only">Menu</span>
        </button>
    </div>

    <!-- container where mobile menu will be injected by JS -->
    <div id="mobileMenuContainer" class="mobile-menu-container d-lg-none" aria-hidden="true"></div>

    <!-- legacy mobile-nav (kept hidden when mobile-bar is present) -->
    <div class="mobile-nav" style="display:none;">
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('uploads/logo.png') }}" alt="Biuro Podróży RAFA – wycieczki szkolne">
        </a>
    </div>

    <!-- Menu For Desktop Device -->
    <div class="main-nav">
        <div class="container">
            <nav class="navbar navbar-expand-md navbar-light">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <img src="{{ asset('uploads/logo.png') }}" alt="Biuro Podróży RAFA – wycieczki szkolne">
                </a>
                <div class="collapse navbar-collapse mean-menu" id="navbarSupportedContent">
                    <ul class="navbar-nav">
                        <li class="nav-item {{ \Illuminate\Support\Facades\Route::is('home') ? 'active' : '' }}">
                            <a href="{{ route('home') }}" class="nav-link" class="nav-link">Start</a>
                        </li>
                        <div class="offer-link">
                            <li class="nav-item">
                                <a href="{{ route('directory-packages')}}" class="nav-link">Oferta wycieczek szkolnych 2026</a>
                                <ul class="dropdown">
                                    <div class="background">
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '1']) }}">Wycieczki jednodniowe</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '2']) }}">Wycieczki dwudniowe</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '3']) }}">Wycieczki trzydniowe</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '4']) }}">Wycieczki czterodniowe</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '5']) }}">Wycieczki pięciodniowe</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('packages', ['length_id' => '6plus']) }}">Wycieczki sześciodniowe i dłuższe</a>
                                        </li>
                                </ul>
                            </li>
                        </div>
                        <li class="nav-item">
                            <a href="{{ route('about.global') }}" class="nav-link">O nas</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('guide.global') }}" class="nav-link">Poradnik</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('blog.global') }}" class="nav-link">Aktualności</a>
                        </li>
                        <li class="nav-item {{ \Illuminate\Support\Facades\Route::is('insurance') ? 'active' : '' }}">
                            <a href="{{ route('insurance') }}" class="nav-link">Ubezpieczenia</a>
                        </li>
                        <li class="nav-item {{ \Illuminate\Support\Facades\Route::is('documents') ? 'active' : '' }}">
                            <a href="{{ route('documents.global') }}" class="nav-link">Dokumenty</a>
                        </li>
                        <li class="nav-item {{ \Illuminate\Support\Facades\Route::is('faq') ? 'active' : '' }}">
                            <a href="{{ route('faq') }}" class="nav-link">FAQ</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('contact') }}" class="nav-link">Kontakt</a>
                        </li>
                    </ul>
                </div>
                <div class="contact-info">
                    <a href="tel:606102243"><div class="phone">
                        <i class="fas fa-phone"></i>+ 48 606 102 243
                        </div></a>
                    <a href="mailto:rafa@bprafa.pl"><div class="mail">
                        <i class="fas fa-envelope"></i> rafa@bprafa.pl
                    </div></a>
                </div>
            </nav>
        </div>
    </div>
</div>
