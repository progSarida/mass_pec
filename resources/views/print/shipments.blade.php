<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            /* Layout fixed per evitare che i colspan variabili deformino le colonne */
            table-layout: fixed;
        }

        th, td {
            padding: 4px;
            text-align: left;
            word-wrap: break-word;
        }

        /* --- LOGICA BORDINI HEADER --- */
        /* Bordo superiore dell'intestazione */
        thead tr:first-child th {
            border-top: 2px solid black;
        }

        /* Bordi laterali dell'intestazione */
        thead th:first-child { border-left: 2px solid black; }
        thead th:last-child { border-right: 2px solid black; }

        /* Chiusura dell'intestazione (bordo inferiore) */
        thead tr.last-row th {
            border-bottom: 2px solid black;
        }

        /* --- LOGICA BORDINI BODY --- */
        /* Bordi laterali esterni per ogni riga del corpo */
        tbody td:first-child { border-left: 2px solid black; }
        tbody td:last-child { border-right: 2px solid black; }

        /* Bordo tratteggiato solo dopo la terza riga di ogni record */
        /* Nota: applichiamo il bordo ai TD, non al TR, per massima compatibilità PDF */
        tbody tr.last-row td {
            border-bottom: 1px dashed black;
        }

        /* Se è l'ultima riga assoluta della tabella, usa il bordo solido invece del tratteggiato */
        tbody tr:last-child td {
            border-bottom: 2px solid black;
        }

        /* Utility per il grassetto */
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <h2 style="text-align: center"><u>Spedizioni</u></h2>

    @php
        $hasActiveFilters = false;

        if (!empty($search)) {
            $hasActiveFilters = true;
        }

        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $filterName => $filterData) {
                if (isset($filterData['value']) && $filterData['value'] !== null && $filterData['value'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
                if (isset($filterData['insert_from_date']) && $filterData['insert_from_date'] !== null && $filterData['insert_from_date'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
                if (isset($filterData['insert_to_date']) && $filterData['insert_to_date'] !== null && $filterData['insert_to_date'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
                if (isset($filterData['send_from_date']) && $filterData['send_from_date'] !== null && $filterData['send_from_date'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
                if (isset($filterData['send_to_date']) && $filterData['send_to_date'] !== null && $filterData['send_to_date'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
            }
        }
    @endphp

    @if($hasActiveFilters)
        <p><strong>'Filtri applicati:'</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @if(!empty($filters['region_id']['value']))
                <li>
                    Regione:
                    @php $regionName = \App\Models\Region::find($filters['region_id']['value'])?->name; @endphp
                    {{ $regionName }}
                </li>
            @endif
            @if(!empty($filters['province_id']['value']))
                <li>
                    Provincia:
                    @php $provinceName = \App\Models\Province::find($filters['province_id']['value'])?->name; @endphp
                    {{ $provinceName }}
                </li>
            @endif
            @if(!empty($filters['send_user_id']['value']))
                <li>
                    Inviate da:
                    @php $username = \App\Models\User::find($filters['send_user_id']['value'])?->name; @endphp
                    {{ $username }}
                </li>
            @endif
            @if(!empty($filters['mail_type']['value']))
                <li>
                    Tipo mail:
                    @php $mailType = \App\Enums\MailType::tryFrom($filters['mail_type']['value'])?->getLabel(); @endphp
                    {{ $mailType }}
                </li>
            @endif
            @if(!empty($filters['sent']['value']))
                <li>
                    @php $sent = $filters['sent']['value'] == 'si' ? 'Inviate' : 'Non inviate'; @endphp
                    {{ $sent }}
                </li>
            @endif
            @if(!empty($filters['delivered']['value']))
                <li>
                    @php $delivered = $filters['delivered']['value'] == 'si' ? 'Tutte consegnate' : 'Non tutte consegnate'; @endphp
                    {{ $delivered }}
                </li>
            @endif
            @if(!empty($filters['is_registered']['value']))
                <li>
                    @php $registeredStatus = $filters['is_registered']['value'] == 'si' ? 'Protocollate' : 'Non protocollate'; @endphp
                    {{ $registeredStatus }}
                </li>
            @endif
            @if(!empty($filters['insert_date_range']['insert_from_date']) || !empty($filters['insert_date_range']['insert_to_date']))
                <li>
                    Data inserimento:
                    @php
                        $fromDate = !empty($filters['insert_date_range']['insert_from_date']) ? \Carbon\Carbon::parse($filters['insert_date_range']['insert_from_date'])->format('d/m/Y') : 'N/D';
                        $toDate = !empty($filters['insert_date_range']['insert_to_date']) ? \Carbon\Carbon::parse($filters['insert_date_range']['insert_to_date'])->format('d/m/Y') : 'N/D';
                        $dateRange = ($fromDate !== 'N/D' && $toDate !== 'N/D') ? "dal $fromDate al $toDate" : ($fromDate !== 'N/D' ? "dal $fromDate" : "al $toDate");
                    @endphp
                    {{ $dateRange }}
                </li>
            @endif
            @if(!empty($filters['send_date_range']['send_from_date']) || !empty($filters['send_date_range']['send_to_date']))
                <li>
                    Data invio:
                    @php
                        $fromDate = !empty($filters['send_date_range']['send_from_date']) ? \Carbon\Carbon::parse($filters['send_date_range']['send_from_date'])->format('d/m/Y') : 'N/D';
                        $toDate = !empty($filters['send_date_range']['send_to_date']) ? \Carbon\Carbon::parse($filters['send_date_range']['send_to_date'])->format('d/m/Y') : 'N/D';
                        $dateRange = ($fromDate !== 'N/D' && $toDate !== 'N/D') ? "dal $fromDate al $toDate" : ($fromDate !== 'N/D' ? "dal $fromDate" : "al $toDate");
                    @endphp
                    {{ $dateRange }}
                </li>
            @endif
        </ul>
    @endif

    <table>
        <thead>
            <tr>
                <th colspan="24">Descrizione</th>
                <th colspan="6">Protocollata</th>
            </tr>
            <tr>
                <th colspan="6">Regione</th>
                <th colspan="6">Provincia</th>
                <th colspan="6">Data inserimento</th>
                <th colspan="6">Data invio</th>
                <th colspan="6">Inviata da</th>
            </tr>
            <tr class="last-row">
                <th colspan="5">Mail totali</th>
                <th colspan="5">Mail da inviare</th>
                <th colspan="5">Mail inviate</th>
                <th colspan="5">Mail accettate</th>
                <th colspan="5">Mail consegnate</th>
                <th colspan="5">Anomalie</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shipments as $shipment)
                @php
                    $stub = App\Models\Registry::where('shipment_id', $shipment->id);
                    $registeredValue = $stub->exists() ? 'Si (' . $stub->first()->protocol_number . ')' : 'No';
                    $regionName = App\Models\Region::find($shipment->region_id)?->name ?? '';
                    $provinceName = App\Models\Province::find($shipment->province_id)?->name ?? '';
                    $sendUser = App\Models\User::find($shipment->send_user_id)?->name ?? '';
                @endphp

                <tr>
                    <td colspan="24">{{ $shipment->description }}</td>
                    <td colspan="6">{{ $registeredValue }}</td>
                </tr>

                <tr>
                    <td colspan="6">{{ $regionName }}</td>
                    <td colspan="6">{{ $provinceName }}</td>
                    <td colspan="6">{{ $shipment->insert_date ? \Carbon\Carbon::parse($shipment->insert_date)->format('d/m/Y') : '' }}</td>
                    <td colspan="6">{{ $shipment->send_date ? \Carbon\Carbon::parse($shipment->send_date)->format('d/m/Y') : '' }}</td>
                    <td colspan="6">{{ $sendUser }}</td>
                </tr>

                <tr class="last-row">
                    <td colspan="5">{{ $shipment->total_no_mails ?? 0 }}</td>
                    <td colspan="5">{{ $shipment->no_mails_to_send ?? 0 }}</td>
                    <td colspan="5">{{ $shipment->no_mails_sended ?? 0 }}</td>
                    <td colspan="5">{{ $shipment->no_send_receipt ?? 0 }}</td>
                    <td colspan="5">{{ $shipment->no_delivery_receipt ?? 0 }}</td>
                    <td colspan="5">{{ $shipment->no_anomaly_receipt ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
