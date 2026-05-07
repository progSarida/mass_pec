<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            /* Layout fixed per evitare che i colspan variabili deformino le colonne */
            table-layout: fixed;
        }

        th, td {
            padding: 1px;
            text-align: left;
            word-wrap: break-word;
            border: 0.5px solid #ccc; /* bordi cell eper test */
        }

        /* Fix per domPDF: se la cella è vuota, aggiunge uno spazio invisibile */
        td:empty::before {
            content: "\00a0";
        }

        /* Rimuovi min-height (che a volte domPDF ignora) e usa line-height */
        tr {
            line-height: 12px;
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

        /* Utility */
        .bold { font-weight: bold; }
        .underline { text-decoration: underline; }

        tbody tr {
            line-height: 14px; /* Forza la riga ad essere alta almeno 14px */
        }

        td {
            height: 14px; /* Opzionale: definisce un'altezza fissa */
        }
    </style>
</head>
<body>
    @php
        /**
         * Configurazione della griglia.
         * Ogni riga dell'array rappresenta una riga della tabella.
         * La somma dei colspan deve essere sempre 24.
         */
        $gridConfig = [
            'row1' => ['scope' => 12, 'account' => 12],
            'row2' => ['subject' => 24,],
            'row3' => ['recipient' => 24,],
            'row4' => ['date' => 12, 'user' => 12],
        ];

        $totalColumns = 24;
    @endphp
    <h2 style="text-align: center"><u>Email ricevute</u></h2>

    @php
        $hasActiveFilters = false;

        if (!empty($search)) {
            $hasActiveFilters = true;
        }

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
    @endphp

    @if($hasActiveFilters)
        <p><strong>'Filtri applicati:'</strong></p>
        <ul>
            @if($search)
                <li>Ricerca: {{ $search }}</li>
            @endif
            @if(!empty($filters['recipient']['values']))
                <li>
                    Mittente:
                    @php
                        // Prendiamo tutti i valori (ID) dai filtri
                        $ids = $filters['recipient']['values'];

                        // Assicuriamoci che sia un array (se fosse un singolo ID stringa)
                        $idsArray = is_array($ids) ? $ids : [$ids];

                        // Facciamo una query unica per tutti i destinatari
                        $senderNames = \App\Models\Recipient::whereIn('id', $idsArray)
                            ->pluck('description')
                            ->join(', ');
                    @endphp
                    {{ $senderNames }}
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
            @if(!empty($filters['account_id']['value']))
                <li>
                    Account:
                    @php $accountName = \App\Models\Account::find($filters['account_id']['value'])?->public_name; @endphp
                    {{ $accountName }}
                </li>
            @endif
        </ul>
    @endif

    <table>
        <colgroup>
            @for ($i = 0; $i < $totalColumns; $i++)
                <col style="width: {{ 100 / $totalColumns }}%;">
            @endfor
        </colgroup>

        <thead>
            <tr>
                <th colspan="{{ $gridConfig['row1']['scope'] }}">Settore interno</th>
                <th colspan="{{ $gridConfig['row1']['account'] }}">Account</th>
            </tr>
            <tr>
                <th colspan="{{ $gridConfig['row2']['subject'] }}">Oggetto</th>
            </tr>
            <tr>
                <th colspan="{{ $gridConfig['row3']['recipient'] }}">Destinatari</th>
            </tr>
            <tr class="last-row">
                <th colspan="{{ $gridConfig['row4']['date'] }}">Data invio</th>
                <th colspan="{{ $gridConfig['row4']['user'] }}">Inviato da</th>
            </tr>
        </thead>

        <tbody>
            @foreach($emails as $email)
                @php
                    $recipientNames = \App\Models\Recipient::whereHas('emails', function($query) use ($email) {
                        $query->whereIn('email', (array) $email->recipients);
                    })
                    ->pluck('description')
                    ->join(', ');
                @endphp

                <tr>
                    <td colspan="{{ $gridConfig['row1']['scope'] }}">{{ \App\Models\ScopeType::find($email->scope_type_id)?->name ?? '-' }}</td>
                    <td colspan="{{ $gridConfig['row1']['account'] }}">{{ \App\Models\Account::find($email->account_id)?->public_name ?? '' }}</td>
                </tr>

                <tr>
                    <td colspan="{{ $gridConfig['row2']['subject'] }}">{{ $email->subject ?? '' }}</td>
                </tr>

                <tr>
                    <td colspan="{{ $gridConfig['row3']['recipient'] }}">{{ $recipientNames ?? '' }}</td>
                </tr>

                <tr class="last-row">
                    <td colspan="{{ $gridConfig['row4']['date'] }}">{{ \Carbon\Carbon::parse($email->send_date)->format('d/m/Y') }}</td>
                    <td colspan="{{ $gridConfig['row4']['user'] }}">{{ \App\Models\User::find($email->send_user_id)?->name ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
