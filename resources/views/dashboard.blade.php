@extends('layout')

@section('content')
    <style>
        .fc-event-title {
            white-space: normal !important;
            font-size: 0.85em;
            line-height: 1.2;
        }
        .fc-event-time {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }
        .fc-daygrid-day {
            cursor: pointer; /* Laat zien dat je kunt klikken */
        }
        .fc-daygrid-day:hover {
            background-color: #f8f9fa; /* Licht effect bij hover */
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
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle"></i> Klik op een datum om de uitgebreide planning te zien.
                </div>
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

    {{-- MODAL VOOR DAGDETAILS (Pop-up) --}}
    <div class="modal fade" id="dayDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="modalDateTitle">Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    
                    {{-- Ochtend Sectie --}}
                    <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">🌅 Ochtend (Vanaf 05:00)</h6>
                    <ul id="listAM" class="list-group list-group-flush mb-3 small"></ul>

                    {{-- Dag Sectie --}}
                    <h6 class="text-warning fw-bold border-bottom pb-1 mb-2" style="color: #d68100 !important;">☀️ Dagdienst (09:00 - 18:00)</h6>
                    <ul id="listDAY" class="list-group list-group-flush mb-3 small"></ul>

                    {{-- Middag Sectie --}}
                    <h6 class="text-success fw-bold border-bottom pb-1 mb-2">🌇 Middag (Vanaf 14:00)</h6>
                    <ul id="listPM" class="list-group list-group-flush mb-3 small"></ul>

                    <div id="noEventsMessage" class="text-center text-muted fst-italic d-none mt-4">
                        Geen diensten gepland voor deze dag.
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Sluiten</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            // Initialiseer Modal
            var myModal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                firstDay: 6, 
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                eventTimeFormat: { 
                    hour: '2-digit', minute: '2-digit', meridiem: false, hour12: false
                },
                displayEventEnd: true,
                events: '/nieuwegein/schedule/api',

                // KLIK EVENT: Wanneer op een datum geklikt wordt
                dateClick: function(info) {
                    var clickedDate = info.dateStr;
                    
                    // Zet datum in titel (even mooi formatteren)
                    var dateObj = new Date(clickedDate);
                    var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    document.getElementById('modalDateTitle').innerText = dateObj.toLocaleDateString('nl-NL', options);

                    // Maak lijsten leeg
                    document.getElementById('listAM').innerHTML = '';
                    document.getElementById('listPM').innerHTML = '';
                    document.getElementById('listDAY').innerHTML = '';
                    document.getElementById('noEventsMessage').classList.add('d-none');

                    // Data ophalen via AJAX
                    fetch('/nieuwegein/schedule/day/' + clickedDate)
                        .then(response => response.json())
                        .then(data => {
                            var hasEvents = false;

                            // Functie om lijst te vullen
                            function fillList(elementId, items) {
                                if (items && items.length > 0) {
                                    hasEvents = true;
                                    items.forEach(item => {
                                        var li = document.createElement('li');
                                        li.className = 'list-group-item d-flex justify-content-between align-items-center px-0 py-1';
                                        li.innerHTML = `<span>${item.name}</span> <span class="badge bg-light text-dark border">${item.time}</span>`;
                                        document.getElementById(elementId).appendChild(li);
                                    });
                                } else {
                                    var li = document.createElement('li');
                                    li.className = 'list-group-item text-muted fst-italic px-0 py-1';
                                    li.innerText = '- Geen personeel -';
                                    document.getElementById(elementId).appendChild(li);
                                }
                            }

                            fillList('listAM', data.AM);
                            fillList('listDAY', data.DAY);
                            fillList('listPM', data.PM);

                            // Toon modal
                            myModal.show();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Kon details niet ophalen.');
                        });
                }
            });
            calendar.render();
        });
    </script>
@endsection