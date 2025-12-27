@extends('layout')

@section('content')
    <style>
        /* CSS om te zorgen dat de tekst in de kalender niet wordt afgekapt */
        .fc-event-title {
            white-space: normal !important; /* Tekst mag naar volgende regel */
            font-size: 0.85em;
            line-height: 1.2;
        }
        .fc-event-time {
            font-weight: bold;
            display: block; /* Tijd op eigen regel */
            margin-bottom: 2px;
        }
        /* Zorgt dat events wat meer ruimte krijgen */
        .fc-daygrid-event {
            padding: 4px;
        }
    </style>

    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Dashboard / Rooster</h5>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <div class="row">
        {{-- Linker kolom: Kalender --}}
        <div class="col-md-8">
            <div class="erp-card">
                <div id='calendar' style="min-height: 700px;"></div>
            </div>
        </div>

        {{-- Rechter kolom: Uren Overzicht --}}
        <div class="col-md-4">
            <div class="erp-card">
                <h6 class="fw-bold mb-3">Totaal gepland (Alle tijden)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Contract</th>
                                <th>Gepland</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td><small class="text-muted">{{ $user->contract_hours }}u p/w</small></td>
                                    <td class="fw-bold">
                                        {{-- Weergeeft het totaal berekend in de controller --}}
                                        {{ $user->planned_hours_total ?? 0 }} u
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <small class="text-muted fst-italic">Totaal van alle ingeplande diensten in de database.</small>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                
                // ZATERDAG ALS EERSTE DAG (0=Zondag, 1=Maandag, ..., 6=Zaterdag)
                firstDay: 6, 
                
                height: 'auto', // Hoogte past zich aan de inhoud aan
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                
                // Formaat van de tijden in de blokjes (bijv. 09:00 - 18:00)
                eventTimeFormat: { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    meridiem: false,
                    hour12: false
                },
                displayEventEnd: true, // Laat ook de eindtijd zien
                
                // Haal de events op uit onze API
                events: '/nieuwegein/schedule/api' 
            });
            calendar.render();
        });
    </script>
@endsection