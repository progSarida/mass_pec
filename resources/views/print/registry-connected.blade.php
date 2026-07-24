<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Stampa Collegamenti Protocollo {{ $ownerRecord->protocol_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        .header { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .company-title { font-size: 16px; font-weight: bold; }
        .doc-title { font-size: 14px; margin-top: 5px; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; word-wrap: break-word; overflow: hidden; }
        th { background-color: #f2f2f2; font-weight: bold; }
        
        /* Definizione larghezze colonne */
        .col-tipo { width: 21%; }
        .col-catena { width: 17%; }
        .col-oggetto { width: 50%; }
        .col-data { width: 12%; }

        .badge { display: inline-block; padding: 2px 6px; font-size: 9px; border-radius: 3px; background: #eee; }
        .page-break { page-break-after: always; }
        .detail-box { border: 1px solid #ccc; padding: 10px; margin-top: 15px; background: #fafafa; }

        /* Contenitore e regole per l'HTML dinamico del messaggio */
        .html-content {
            margin-top: 8px;
            padding: 8px;
            background: #ffffff;
            border: 1px dashed #ddd;
            word-wrap: break-word;
            overflow: hidden;
        }
        /* Previene che immagini o tabelle dentro l'email rompano il layout del PDF */
        .html-content img { max-width: 100% !important; height: auto !important; }
        .html-content table { width: 100% !important; table-layout: fixed; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-title">{{ $company?->name ?? 'Azienda' }}</div>
        <div class="doc-title">Rete Collegamenti - Protocollo Padre: <strong>{{ $ownerRecord->protocol_number }}</strong></div>
        <small>Data stampa: {{ now()->format('d/m/Y H:i') }}</small>
    </div>

    <h3>Riepilogo Grafico della Rete</h3>
    <table>
        <thead>
            <tr>
                <th class="col-tipo">Tipo Relazione</th>
                <th class="col-catena">Catena di Collegamento</th>
                <th class="col-oggetto">Oggetto</th>
                <th class="col-data">Data Reg.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($connectedRecords as $record)
                @php
                    $meta = $linkMeta[$record->id] ?? [];
                    $fromId = $meta['from'] ?? null;
                    $fromProtocol = $fromId 
                        ? (\App\Models\Registry::find($fromId)?->protocol_number ?? '?') 
                        : $ownerRecord->protocol_number;
                    $type = \App\Enums\RelationshipType::from($meta['relationship_type'])->getRelationLabel($meta['direction'], $meta['depth']) ?? 'Correlato';
                @endphp
                <tr>
                    <td><span class="badge">{{ $type }}</span></td>
                    <td><strong>{{ $fromProtocol }}</strong> &rarr; <strong>{{ $record->protocol_number }}</strong></td>
                    <td>{{ $record->subject }}</td>
                    <td>{{ $record->created_at?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">Nessun collegamento presente.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Se l'utente ha spuntato "Includi scheda dettagliata per ogni voce" --}}
    @if($includeDetails && $connectedRecords->isNotEmpty())
        <div class="page-break"></div>
        <h2>Dettaglio Singole Voci Collegate</h2>

        @foreach($connectedRecords as $record)
            @php
                $rawBody = $record->eml_body ?? $record->body;
            @endphp
            <div class="detail-box">
                <h4>Protocollo: {{ $record->protocol_number }}</h4>
                <p><strong>Data:</strong> {{ $record->created_at?->format('d/m/Y H:i') }}</p>
                <p><strong>Mittente:</strong> {{ $record->sender?->description ?? 'N/D' }}</p>
                <p><strong>Oggetto:</strong> {{ $record->subject }}</p>
                
                <p><strong>Messaggio:</strong></p>
                <div class="html-content">
                    {!! $rawBody !!}
                </div>
                
                @if($record->notes)
                    <p style="margin-top: 10px;"><strong>Note:</strong> {{ $record->notes }}</p>
                @endif
            </div>
            <br>
        @endforeach
    @endif

</body>
</html>