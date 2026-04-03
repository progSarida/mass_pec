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

        tr td,
        tr th {
            border-left: 2px solid black;
            border-right: 2px solid black;
            border-bottom: 1px dashed black;
        }

        /* No border for first and second rows of the same bidding */
        tr.bidding-first-row,
        tr.bidding-second-row {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center"><u>Errori spedizione '{{ $shipment->description}}'</u></h2>
    <table>
        <thead>
            <tr class="header">
                <th>Interlocutore</th>
                <th>Indirizzo</th>
                <th>Data invio</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($errors as $error)
                <tr>
                    <td>{{ $error->recipient?->description ?? '' }}</td>
                    <td>{{ $error->address ?? '' }}</td>
                    <td>{{ $error->send_date ? \Carbon\Carbon::parse($error->send_date)->format('d/m/Y') : \Carbon\Carbon::parse($error->receive_date)->format('d/m/Y') }}</td>
                    <td>{{ $error->shipment_error_type?->getLabel() ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
