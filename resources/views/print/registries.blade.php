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

        /* thead tr.top {
            border-top: 2px solid black;
        }

        thead tr.bottom {
            border-bottom: 2px solid black;
        } */

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

        /* Solid border only after the third row of each bidding */
        tr.bidding-third-row {
            border-bottom: 2px dashed black;
        }

        /* No border for first and second rows of the same bidding */
        tr.bidding-first-row,
        tr.bidding-second-row {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center"><u>Registro Protocollo</u></h2>
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

        $filters = $hasActiveFilters ? 'Filtri applicati:' : 'Nessun filtro applicato';
    @endphp
    <p><strong>{{ $filters }}</strong></p>
    @if($hasActiveFilters)
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @if(!empty($filters['sender']['values']))
                <li>
                    Mittente:
                    @php
                        // Prendiamo tutti i valori (ID) dai filtri
                        $ids = $filters['sender']['values'];

                        // Assicuriamoci che sia un array (se fosse un singolo ID stringa)
                        $idsArray = is_array($ids) ? $ids : [$ids];

                        // Facciamo una query unica per tutti i mittenti
                        $senderNames = \App\Models\Recipient::whereIn('id', $idsArray)
                            ->pluck('description')
                            ->join(', ');
                    @endphp
                    {{ $senderNames }}
                </li>
            @endif
            @if(!empty($filters['recipient']['values']))
                <li>
                    Destinatari:
                    @php
                        // Prendiamo tutti i valori (ID) dai filtri
                        $ids = $filters['recipient']['values'];

                        // Assicuriamoci che sia un array (se fosse un singolo ID stringa)
                        $idsArray = is_array($ids) ? $ids : [$ids];

                        // Facciamo una query unica per tutti i mittenti
                        $senderNames = \App\Models\Recipient::whereIn('id', $idsArray)
                            ->pluck('description')
                            ->join(', ');
                    @endphp
                    {{ $senderNames }}
                </li>
            @endif
            @if(!empty($filters['flow_type']['value']))
                <li>
                    Corrispondenza:
                    @php
                        $flowType = \App\Enums\FlowType::tryFrom($filters['flow_type']['value'])?->getLabel();
                    @endphp
                    {{ $flowType }}
                </li>
            @endif
            @if(!empty($filters['is_email']['value']))
                <li>
                    Tipo:
                    @php
                        $isEmail = $filters['is_email']['value'] == 'si' ? 'Posta elettronica' : 'Posta ordinaria';
                    @endphp
                    {{ $isEmail }}
                </li>
            @endif
            @if(!empty($filters['registration_date_range']['registration_from_date']) || !empty($filters['registration_date_range']['registration_to_date']))
                <li>
                    Data registrazione:
                    @php
                        $fromDate = $filters['registration_date_range']['registration_from_date'] ? \Carbon\Carbon::parse($filters['registration_date_range']['registration_from_date'])->format('d/m/Y') : 'N/D';
                        $toDate = $filters['registration_date_range']['registration_to_date'] ? \Carbon\Carbon::parse($filters['registration_date_range']['registration_to_date'])->format('d/m/Y') : 'N/D';
                        $dateRange = $fromDate !== 'N/D' && $toDate !== 'N/D' ? "dal $fromDate al $toDate" : ($fromDate !== 'N/D' ? "dal $fromDate" : "al $toDate");
                    @endphp
                    {{ $dateRange }}
                </li>
            @endif
            @if(!empty($filters['registry_origin_type']['value']))
                <li>
                    Origine:
                    @php
                        $originType = \App\Enums\RegistryOriginType::tryFrom($filters['registry_origin_type']['value'])?->getLabel();
                    @endphp
                    {{ $originType }}
                </li>
            @endif
            @if(!empty($filters['register_user_id']['value']))
                <li>
                    Registrato da:
                    @php
                        $username = \App\Models\User::find($filters['register_user_id']['value'])?->name;
                    @endphp
                    {{ $username }}
                </li>
            @endif
            @if(!empty($filters['esito_invio']['value']))
                <li>
                    Esito invio:
                    @php
                        switch($filters['esito_invio']['value']){
                            case 'non_inviato':
                                $outcome = 'Non inviato';
                                break;
                            case 'consegnato':
                                $outcome = 'Tutto consegnato';
                                break;
                            case 'parziale':
                                $outcome = 'Consegnato parzialmente';
                                break;
                        }
                    @endphp
                    {{ $outcome }}
                </li>
            @endif
            @if(!empty($filters['manage_registry_type']['values']))
                <li>
                    Gestione:
                    @php
                        $manageRegistryTypeValues = array_map(function ($value) {
                            return \App\Enums\ManageRegistryType::tryFrom($value)?->getLabel() ?? $value;
                        }, $filters['manage_registry_type']['values']);
                    @endphp
                    {{ implode(', ', $manageRegistryTypeValues) }}
                </li>
            @endif
        </ul>
    @endif
    <table>
        <thead>
            <tr class="header">
                <th>Numero protocollo</th>
                <th>Tipo</th>
                <th>Data registrazione</th>
                <th>Data atto</th>
                <th>Settore interno</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registries as $registry)
            @php
                $recipientLabel = count($registry->registryReceivers) <= 1 ? 'Interlocutore' : 'Interlocutori';
                if($registry->flow_type == \App\Enums\FlowType::ISSUED)
                    $recipient = $registry->registryReceivers?->pluck('recipient.description')->filter()->join(', ');
                else if ($registry->flow_type == \App\Enums\FlowType::RECEIVED)
                    $recipient = $registry->sender?->description;
            @endphp
                <tr class="bidding-first-row">
                    <td>{{ $registry->protocol_number ?? '' }}</td>
                    <td>{{ $registry->flow_type?->getLabel() ?? '' }}</td>
                    <td>{{ $registry->created_at ? \Carbon\Carbon::parse($registry->created_at)->format('d/m/Y') : '' }}</td>
                    <td>{{ $registry->send_date ? \Carbon\Carbon::parse($registry->send_date)->format('d/m/Y') : \Carbon\Carbon::parse($registry->receive_date)->format('d/m/Y') }}</td>
                    <td>{{ $registry->scopeType?->name ?? '' }}</td>
                </tr>
                <tr class="bidding-second-row">
                    <td colspan="5">
                        <strong>{{ $recipientLabel }}:</strong> {{ $recipient }}
                    </td>
                </tr>
                <tr class="bidding-third-row">
                    <td colspan="5">
                        <strong>Oggetto:</strong> {{ Str::limit($registry->subject, 120, '...') ?: '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
