@php
    /** @var array<int, \App\Support\PilotSetFinanceCard> $pilotSetFinanceCards */
    $cards = collect($pilotSetFinanceCards ?? [])->values();
@endphp

@if($cards->isNotEmpty())
    <div class="section">
        <div class="section-title">Finanse setów</div>
        <div class="section-body">
            @foreach($cards as $card)
                <table class="rows" style="margin-bottom:10px;">
                    <tr>
                        <td class="lbl" style="vertical-align:top; width:32%;">Set</td>
                        <td class="val">
                            <strong>{{ $card->parentName }}</strong> (dzień {{ $card->day }})
                            @if(! $card->inProgram)
                                <br><small>Poza programem</small>
                            @endif
                        </td>
                    </tr>
                    @if($card->hasPilotObligation)
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Do zapłaty (pilot)</td>
                            <td class="val">
                                {{ $card->totalPilotDueLabel }}
                                <small>(plan: {{ $card->plannedPilotLabel }})</small>
                            </td>
                        </tr>
                    @endif
                    @if($card->memberLines !== [])
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Rozbicie</td>
                            <td class="val">
                                <ul style="margin:0; padding-left:14px;">
                                    @foreach($card->memberLines as $line)
                                        <li style="margin-bottom:2px;">{{ $line->displayLabel }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif
                </table>
            @endforeach
        </div>
    </div>
@endif
