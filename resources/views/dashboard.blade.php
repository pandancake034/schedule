@extends('layout')

@section('content')
    <style>
        /* --- ERP / Google Calendar Look Styling --- */
        
        /* Algemene Kalender Styling */
        #calendar {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Header (Ma/Di/Wo...) */
        .fc-col-header-cell {
            background-color: #f8fafc;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .fc-col-header-cell-cushion {
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dag Cellen */
        .fc-daygrid-day-number {
            font-size: 0.85rem;
            color: #64748b;
            padding: 4px 8px;
            text-decoration: none;
        }
        .fc-daygrid-day:hover {
            background-color: #f8fafc;
        }
        .fc-day-today {
            background-color: #fff !important; /* Reset geel naar wit */
        }
        .fc-day-today .fc-daygrid-day-number {
            background-color: #0f6cbd;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 4px;
        }

        /* EVENT CHIPS (De Google Look) */
        .fc-event {
            border: none !important;
            border-radius: 3px;
            font-size: 0.75rem; /* Klein en compact */
            padding: 1px 4px;
            margin-bottom: 1px;
            cursor: pointer;
            box-shadow: none;
            line-height: 1.4;
        }
        
        /* Zorgt dat tekst niet buiten het blokje valt */
        .fc-event-main {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        /* De "+2 meer" link */
        .fc-daygrid-more-link {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 600;
            text-decoration: none;
            padding-left: 4px;
        }
        .fc-daygrid-more-link:hover {
            text-decoration: underline;
            color: #0f6cbd;
        }

        /* Popover (als je op +2 meer klikt) */
        .fc-popover {
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            z-index: 1050; /* Boven alles */
        }
        .fc-popover-header {
            background-color: #f1f5f9;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 12px;
            color: #334155;
        }
        .fc-popover-body {
            padding: 8px;
        }
    </style>

    <div class="top-bar">
        <h5><i class="bi bi-calendar-week me-2"></i>Dashboard</h5>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-light text-dark border"><i class="bi bi-circle-fill text-primary me-1" style="font-size:0.6rem"></i> Ochtend</span>
            <span class="badge bg-light text-dark border"><i class="bi bi-circle-fill text-success me-1" style="font-size:0.6rem"></i> Middag</span>
            <span class="badge bg-light text-dark border"><i class="bi bi-circle-fill me-1" style="font-size:0.6rem; color: #d68100;"></i> Dagdienst</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center py-2 px-3 rounded-1 border-0 border-start border-4 border-success shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            <small class="fw-medium">{{ session('success') }}</small>
        </div>
    @endif

    <div class="row g-3 h-100">
        {{-- Linker kolom: Kalender --}}
        <div class="col-lg-9">
            <div class="erp-card p-0 h-100 shadow-sm">
                <div class="p-3 bg-white h-100">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>

        {{-- Rechter kolom: Uren --}}
        <div class="col-lg-3">
            <div class="erp-card h-100 shadow-sm" style="max-height: 800px; overflow-y: auto;">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h6 class="fw-bold text-secondary mb-0">Uren deze week</h6>
                    <small class="text-muted">Max 40u</small>
                </div>
                
                <table class="table table-sm table-borderless align-middle" style="font-size: 0.85rem;">
                    <tbody>
                        @foreach($stats as $user)
                        <tr class="border-bottom">
                            <td class="py-2 ps-0">
                                <div class="fw-bold text-dark">{{ $user->name }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    Contract: {{ $user->contract_hours }}u
                                </div>
                            </td>
                            <td class="text-end py-2 pe-0">
                                @php
                                    $planned = $user->planned_hours_total;
                                    $contract = $user->contract_hours;
                                    $max = 40;
                                    
                                    // Logic voor kleur
                                    $color = 'bg-primary'; 
                                    if($planned > $contract) $color = 'bg-warning text-dark';
                                    if($planned >= 38) $color = 'bg-success'; 
                                    if($planned > 40) $color = 'bg-danger';

                                    // Percentage voor progress bar (max 40u is 100%)
                                    $width = min(100, ($planned / 40) * 100);
                                @endphp
                                <span class="badge {{ $planned > 40 ? 'bg-danger' : 'bg-light text-dark border' }}">
                                    {{ $planned }}u
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- DETAIL MODAL (Voor als je op een dag klikt) --}}
    <div class="modal fade" id="dayDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-2">
                <div class="modal-header py-2 bg-light border-bottom">
                    <h6 class="modal-title fw-bold text-dark" id="modalDateTitle">Details</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="loadingSpinner" class="text-center p-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                    
                    <div id="detailsContent" class="d-none">
                        {{-- Ochtend --}}
                        <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center">
                            <i class="bi bi-sunrise-fill text-primary me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">OCHTEND</span>
                        </div>
                        <ul id="listAM" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>

                        {{-- Dag --}}
                        <div class="px-3 py-2 bg-light border-bottom border-top d-flex align-items-center">
                            <i class="bi bi-sun-fill text-warning me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">DAGDIENST</span>
                        </div>
                        <ul id="listDAY" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>

                        {{-- Middag --}}
                        <div class="px-3 py-2 bg-light border-bottom border-top d-flex align-items-center">
                            <i class="bi bi-sunset-fill text-success me-2"></i>
                            <span class="fw-bold text-secondary" style="font-size:0.75rem">MIDDAG</span>
                        </div>
                        <ul id="listPM" class="list-group list-group-flush mb-0" style="font-size: 0.8rem;"></ul>
                    </div>

                    <div id="noEventsMessage" class="p-4 text-center text-muted small d-none">
                        Geen planning op deze dag.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var myModal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                firstDay: 6, // Zaterdag
                height: 'auto',
                contentHeight: 700, // Vaste hoogte voor strakke look
                
                // HEADER CONFIG
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                buttonText: {
                    today: 'Vandaag',
                    month: 'Maand'
                },

                // GOOGLE CALENDAR LOOK SETTINGS
                dayMaxEvents: 4, // Toon max 4 regels, daarna "+2 meer"
                moreLinkClick: 'popover', // Opent een nette popover
                fixedWeekCount: false, // Geen lege rijen onderaan de maand
                showNonCurrentDates: false, // Verberg dagen van vorige/volgende maand voor rust

                // CONTENT FORMAT
                eventTimeFormat: { 
                    hour: '2-digit', minute: '2-digit', meridiem: false
                },
                
                events: '/nieuwegein/schedule/api',

                // CUSTOM RENDER VOOR DE "CHIP" LOOK
                eventContent: function(arg) {
                    let timeText = arg.timeText;
                    let title = arg.event.title;
                    
                    // We maken de tijd heel subtiel of verbergen hem als de naam lang is
                    // Hier kiezen we voor: [05:00 Pietje]
                    let html = `<div class="d-flex align-items-center overflow-hidden">`;
                    if(timeText) {
                        html += `<span class="opacity-75 me-1" style="font-size: 0.7em; min-width: 28px;">${timeText}</span>`;
                    }
                    html += `<span class="fw-medium text-truncate">${title}</span>`;
                    html += `</div>`;
                    
                    return { html: html };
                },

                // KLIK OP DAG -> OPEN DETAILS
                dateClick: function(info) {
                    var clickedDate = info.dateStr;
                    var dateObj = new Date(clickedDate);
                    var options = { weekday: 'long', day: 'numeric', month: 'long' };
                    document.getElementById('modalDateTitle').innerText = dateObj.toLocaleDateString('nl-NL', options);

                    // Reset modal
                    document.getElementById('loadingSpinner').classList.remove('d-none');
                    document.getElementById('detailsContent').classList.add('d-none');
                    document.getElementById('noEventsMessage').classList.add('d-none');
                    document.getElementById('listAM').innerHTML = '';
                    document.getElementById('listPM').innerHTML = '';
                    document.getElementById('listDAY').innerHTML = '';

                    myModal.show();

                    fetch('/nieuwegein/schedule/day/' + clickedDate)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('loadingSpinner').classList.add('d-none');
                            
                            var hasEvents = false;
                            
                            // Helper om lijstjes te vullen
                            function fillList(listId, items) {
                                if (items && items.length > 0) {
                                    hasEvents = true;
                                    items.forEach(item => {
                                        var li = document.createElement('li');
                                        li.className = 'list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-0';
                                        li.innerHTML = `
                                            <span class="text-dark">${item.name}</span>
                                            <span class="badge bg-light text-secondary border fw-normal">${item.time}</span>
                                        `;
                                        document.getElementById(listId).appendChild(li);
                                    });
                                } else {
                                    var li = document.createElement('li');
                                    li.className = 'list-group-item text-muted small fst-italic px-3 py-1 border-0';
                                    li.innerText = '-';
                                    document.getElementById(listId).appendChild(li);
                                }
                            }

                            fillList('listAM', data.AM);
                            fillList('listDAY', data.DAY);
                            fillList('listPM', data.PM);

                            if(hasEvents) {
                                document.getElementById('detailsContent').classList.remove('d-none');
                            } else {
                                document.getElementById('noEventsMessage').classList.remove('d-none');
                            }
                        });
                }
            });
            calendar.render();
        });
    </script>
@endsection