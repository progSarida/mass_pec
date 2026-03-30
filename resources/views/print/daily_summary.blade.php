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

        /* Solid border only after the third row of each registry */
        tr.registry-third-row {
            border-bottom: 2px dashed black;
        }

        /* No border for first and second rows of the same registry */
        tr.registry-first-row,
        tr.registry-second-row {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center"><u>Registro Protocollo Giornaliero</u></h2>
    <p><strong>Data:</strong> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</p>
    <p><strong>Dal n. {{ $year }}-{{ Str::padLeft($fromNumber, 5, '0') }} al n. {{ $year }}-{{ $toNumber }}</strong></p>

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
                    $recipient = $registry->registryReceivers?->pluck('recipient.description')->join(', ');
                else if ($registry->flow_type == \App\Enums\FlowType::RECEIVED)
                    $recipient = $registry->sender->description ?? '';
                else
                    $recipient = '';
            @endphp
                <tr class="registry-first-row">
                    <td>{{ $registry->protocol_number ?? '' }}</td>
                    <td>{{ $registry->flow_type?->getLabel() ?? '' }}</td>
                    <td>{{ $registry->created_at ? \Carbon\Carbon::parse($registry->created_at)->format('d/m/Y H:i') : '' }}</td>
                    <td>{{ $registry->send_date ? \Carbon\Carbon::parse($registry->send_date)->format('d/m/Y') : ($registry->receive_date ? \Carbon\Carbon::parse($registry->receive_date)->format('d/m/Y') : '') }}</td>
                    <td>{{ $registry->scopeType?->name ?? '' }}</td>
                </tr>
                <tr class="registry-second-row">
                    <td colspan="5">
                        <strong>{{ $recipientLabel }}:</strong> {{ $recipient }}
                    </td>
                </tr>
                <tr class="registry-third-row">
                    <td colspan="5">
                        <strong>Oggetto:</strong> {{ Str::limit($registry->subject, 120, '...') ?: '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
