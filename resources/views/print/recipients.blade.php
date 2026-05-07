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
            /* border: 0.5px solid #ccc; bordi cell eper test */
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
            'row1' => ['desc' => 20, 'nat' => 4],
            'row2' => ['tipo' => 12, 'cf' => 3, 'piva' => 3, 'tel' => 3, 'fax' => 3],
            'row3' => ['addr' => 10, 'comune' => 6, 'cap' => 2, 'prov' => 2, 'reg' => 4],
            'row4' => ['blank' => 2, 'email' => 11, 'mail_type' => 4, 'office' => 7],
        ];

        $totalColumns = 24;
    @endphp
    <h2 style="text-align: center"><u>Interlocutori</u></h2>

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
            @if(!empty($filters['city_id']['value']))
                <li>
                    Comune:
                    @php $cityName = \App\Models\City::find($filters['city_id']['value'])?->name; @endphp
                    {{ $cityName }}
                </li>
            @endif
            @if(!empty($filters['admin_type_id']['value']))
                <li>
                    Tipo interlocutore:
                    @php $adminType = \App\Models\AdminType::find($filters['admin_type_id']['value'])?->name; @endphp
                    {{ $adminType }}
                </li>
            @endif
            @if(!empty($filters['istat_type_id']['value']))
                <li>
                    Tipo Istat:
                    @php $istatType = \App\Models\IstatType::find($filters['istat_type_id']['value'])?->name; @endphp
                    {{ $istatType }}
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
                <th colspan="{{ $gridConfig['row1']['desc'] }}">Nome e Cognome/Denominazione</th>
                <th colspan="{{ $gridConfig['row1']['nat'] }}">Natura</th>
            </tr>
            <tr>
                <th colspan="{{ $gridConfig['row2']['tipo'] }}">Tipo interlocutore</th>
                <th colspan="{{ $gridConfig['row2']['cf'] }}">Codice Fiscale</th>
                <th colspan="{{ $gridConfig['row2']['piva'] }}">Partita IVA</th>
                <th colspan="{{ $gridConfig['row2']['tel'] }}">Telefono</th>
                <th colspan="{{ $gridConfig['row2']['fax'] }}">Fax</th>
            </tr>
            <tr>
                <th colspan="{{ $gridConfig['row3']['addr'] }}">Indirizzo</th>
                <th colspan="{{ $gridConfig['row3']['comune'] }}">Comune</th>
                <th colspan="{{ $gridConfig['row3']['cap'] }}">Cap</th>
                <th colspan="{{ $gridConfig['row3']['prov'] }}">Provincia</th>
                <th colspan="{{ $gridConfig['row3']['reg'] }}">Regione</th>
            </tr>
            <tr class="last-row">
                <th colspan="{{ $gridConfig['row4']['blank'] }}">Emails</th>
                <th colspan="{{ $gridConfig['row4']['email'] }}">Indirizzo</th>
                <th colspan="{{ $gridConfig['row4']['mail_type'] }}">Tipo</th>
                <th colspan="{{ $gridConfig['row4']['office'] }}">Ufficio</th>
            </tr>
        </thead>

        <tbody>
            @foreach($recipients as $recipient)
                @php
                    $city = $recipient->city;
                    $province = $city?->province;
                    $region = $province?->region;
                @endphp

                <tr>
                    <td colspan="{{ $gridConfig['row1']['desc'] }}" class="underline">{{ $recipient->description ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row1']['nat'] }}">
                        {{ \App\Enums\RecipientType::tryFrom($recipient->recipient_type)?->getLabel() ?? '' }}
                    </td>
                </tr>

                <tr>
                    <td colspan="{{ $gridConfig['row2']['tipo'] }}">{{ $recipient->adminType?->name ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row2']['cf'] }}">{{ $recipient->tax_code ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row2']['piva'] }}">{{ $recipient->vat_code ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row2']['tel'] }}">{{ $recipient->phone ?? ''}}</td>
                    <td colspan="{{ $gridConfig['row2']['fax'] }}">{{ $recipient->fax ?? ''}}</td>
                </tr>

                <tr>
                    <td colspan="{{ $gridConfig['row3']['addr'] }}">{{ $recipient->address ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row3']['comune'] }}">{{ $city?->name ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row3']['cap'] }}">{{ $recipient->city_cap ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row3']['prov'] }}">{{ $province?->code ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row3']['reg'] }}">{{ $region?->name ?? '' }}</td>
                </tr>

                @foreach ($recipient->emails as $email)
                <tr class="{{ $loop->last ? 'last-row' : '' }}">
                    <td colspan="{{ $gridConfig['row4']['blank'] }}" style="text-align: right; padding-right: 5px">•</td>
                    <td colspan="{{ $gridConfig['row4']['email'] }}">{{ $email->email ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row4']['mail_type'] }}">{{ $email->mail_type?->getLabel() ?? '' }}</td>
                    <td colspan="{{ $gridConfig['row4']['office'] }}">{{ $email->officeType?->name ?? '' }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
