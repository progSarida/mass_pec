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
            border: 2px solid black;
        }

        th, td {
            border-left: none;
            border-right: none;
            padding: 4px;
            text-align: left;
        }

        thead tr.header {
            border-top: 2px solid black;
            border-bottom: 2px solid black;
        }

        tr td:first-child,
        tr th:first-child {
            border-left: 2px solid black;
        }

        tr td:last-child,
        tr th:last-child {
            border-right: 2px solid black;
        }

        tbody tr {
            border-bottom: 1px solid #ccc;
        }

        tbody tr:last-child {
            border-bottom: 2px solid black;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center"><u>Elenco Registri Giornalieri</u></h2>

    @php
        $hasActiveFilters = false;

        // Controlla se c'è una ricerca testuale
        if (!empty($search)) {
            $hasActiveFilters = true;
        }

        // Controlla se ci sono filtri attivi (con valori)
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $filterName => $filterData) {
                if (isset($filterData['values']) && !empty($filterData['values'])) {
                    $hasActiveFilters = true;
                    break;
                }
                if (isset($filterData['value']) && $filterData['value'] !== null && $filterData['value'] !== '') {
                    $hasActiveFilters = true;
                    break;
                }
            }
        }

        $filterLabel = $hasActiveFilters ? 'Filtri applicati:' : 'Nessun filtro applicato';
    @endphp

    <p><strong>{{ $filterLabel }}</strong></p>

    @if($hasActiveFilters)
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif

            @if(!empty($filters['registration_date_range']['registration_from_date']) || !empty($filters['registration_date_range']['registration_to_date']))
                <li>
                    Data registrazione:
                    @php
                        $fromDate = $filters['registration_date_range']['registration_from_date']
                            ? \Carbon\Carbon::parse($filters['registration_date_range']['registration_from_date'])->format('d/m/Y')
                            : 'N/D';
                        $toDate = $filters['registration_date_range']['registration_to_date']
                            ? \Carbon\Carbon::parse($filters['registration_date_range']['registration_to_date'])->format('d/m/Y')
                            : 'N/D';
                        $dateRange = $fromDate !== 'N/D' && $toDate !== 'N/D'
                            ? "dal $fromDate al $toDate"
                            : ($fromDate !== 'N/D' ? "dal $fromDate" : "al $toDate");
                    @endphp
                    {{ $dateRange }}
                </li>
            @endif

            @if(!empty($filters['protocol_in_range']['protocol']))
                <li>Numero di protocollo: {{ str_pad($filters['protocol_in_range']['protocol'], 5, '0', STR_PAD_LEFT) }}</li>
            @endif

            @if(isset($filters['preservation_state']['value']))
                <li>
                    Stato preservazione:
                    @php
                        $preservationState = $filters['preservation_state']['value'];
                        if ($preservationState === '') {
                            $stateLabel = 'Non specificato';
                        } else {
                            $stateLabel = \App\Enums\PreservationState::tryFrom($preservationState)?->getLabel() ?? $preservationState;
                        }
                    @endphp
                    {{ $stateLabel }}
                </li>
            @endif
        </ul>
    @endif

    <table>
        <thead>
            <tr class="header">
                <th style="width: 18%">Data Registrazione</th>
                <th style="width: 12%">Nome File</th>
                <th style="width: 18%">Data Creazione</th>
                <th style="width: 15%">Da Protocollo</th>
                <th style="width: 15%">A Protocollo</th>
                <th style="width: 22%">Stato Preservazione</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registries as $registry)
                <tr>
                    <td>{{ $registry->registration_date ? \Carbon\Carbon::parse($registry->registration_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ $registry->filename ?? '' }}</td>
                    <td>{{ $registry->file_date ? \Carbon\Carbon::parse($registry->file_date)->format('d/m/Y H:i:s') : '' }}</td>
                    <td>{{ $registry->from_protocol ?? '' }}</td>
                    <td>{{ $registry->to_protocol ?? '' }}</td>
                    <td>{{ $registry->preservation_state?->getLabel() ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
