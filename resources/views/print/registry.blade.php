<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.4;
            color: #333;
            margin: 20px;
            font-size: 8pt; /* Dimensione base per il testo normale */
        }

        /* 1. Nome Azienda */
        .titolo-azienda {
            font-size: 14pt;
            text-transform: uppercase;
        }

        /* 2. Tipo Posta e Protocollo */
        .intestazione-protocollo {
            font-size: 12pt;
        }

        /* 3. Titoli Sezioni: OGGETTO, TESTO, ALLEGATI */
        .titolo-sezione {
            font-size: 12pt;
            display: inline-block;
            margin-top: 10px;
            /* border-bottom: 1px solid #ccc; */
            width: 100%;
        }

        /* 4. Mittente, Destinatari */
        .partecipanti {
            font-size: 10pt;
        }

        /* 5. Corpo testo */
        .contenuto-testo {
            font-size: 8pt;
        }

        /* Reset liste per allegati */
        ul {
            list-style-type: disc;
            padding-left: 30px;
            margin-top: 5px;
            font-size: 10pt;
        }

        li {
            font-size: 8pt; /* Quarto maggiore */
        }
    </style>
</head>
<body>
    <b class="titolo-azienda">{{ $company->name }}</b><br>
    <br>
    <br>
    @php
        $tipo = $registry->is_email ? 'Posta Elettronica' : 'Posta Orinaria';
        switch($registry->flow_type){
            case \App\Enums\FlowType::ISSUED :
                $data = $registry->send_date;
                $mittente = $registry->shipment_id ? \App\Models\Sender::first()->address . " (" : $registry->account->address . " (";
                $mittente .= $registry->shipment_id ? \App\Models\Sender::first()->public_name : $registry->account->public_name;
                $mittente .= ")";
                $destinatario = '';
                if($registry->shipment_id){
                    $spedizione = $registry->shipment;
                    if($spedizione->province_id) $destinatario = 'Enti provincia di ' . $spedizione->province->name . ': ';
                    else $destinatario = 'Enti regione ' . $spedizione->region->name . ': ';
                    foreach ($spedizione->receivers as $index => $receiver) {
                        if ($index >= 10) {
                            $destinatario .= ', ...';
                            break;
                        }

                        if ($index !== 0) $destinatario .= ', ';
                        $destinatario .= $receiver->address;
                    }
                    if(count($spedizione->receivers) > 10) $destinatario .= ', ...';
                }
                else{
                    foreach($registry->registryReceivers as $key => $receiver){
                        if($key !== 0) $destinatario .= ', ';
                        $destinatario .= $receiver->recipient->description;
                    }
                }
                break;
            case \App\Enums\FlowType::RECEIVED :
                $data = $registry->receive_date;
                $mittente = $registry->from . " (" . $registry->sender->description . ")";
                $destinatario = $registry->receiving_mail . " (" . \App\Models\Account::where('address', $registry->receiving_mail)->first()->public_name . ")";
                break;
            default :
                $data = $registry->created_at;
                $mittente = 'self';
                $destinatario = 'self';
                break;
        }
    @endphp
    <b class="intestazione-protocollo">{{ $tipo }} id {{ $registry->id }} del {{ $data->format('d.m.Y') }} ({{ $data->format('H:m:s') }})</b><br>
    <b class="intestazione-protocollo">PROTOCOLLO n. {{ $registry->protocol_number }} del {{ $registry->created_at->format('d.m.Y') }} ({{ $registry->flow_type->getLabel() }})</b><br>
    <br>
    <br>
    @php

    @endphp
    @if($mittente !== 'self' && $destinatario !== 'self')
    <div class="partecipanti">
        <b>Mittente:</b> {{ $mittente }}<br>
        <b>Destinatari:</b> {{ $destinatario }}
    </div>
    <br>
    @endif
    <b class="titolo-sezione">OGGETTO</b><br>
    <div class="contenuto-testo">
        {!! $registry->subject !!}
    </div>
    <br>
    <b class="titolo-sezione">TESTO</b>
    <br>
    <div class="contenuto-testo">
        {{-- {!! $registry->eml_body ?? $registry->body !!}<br> --}}
        {!!
            preg_replace(
                '/^(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)|(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)$/iu',
                '',
                $registry->eml_body ?? $registry->body
            )
        !!}
    </div>
    <br>
    @if(count($registry->registryReceivers) > 0)
    <b class="titolo-sezione">ACCETTAZIONI / CONSEGNE</b><br>
    <ul style="list-style-type: disc;">
    @foreach ($registry->registryReceivers as $receiver)
        <li>{{ $receiver->address }}: {{ $receiver->pec_status->getLabel() }}</li>
    @endforeach
    </ul>
    <br>
    @endif
    @php
        use Illuminate\Support\Facades\Storage;

        $files = [];
        if ($registry->attachment_path) {
            // Usa Storage::files proprio come fai nella risorsa Filament
            $files = Storage::files($registry->attachment_path);
        }
    @endphp
    <b class="titolo-sezione">ALLEGATI</b><br>
    @if(count($files) > 0)
        <ul style="list-style-type: disc;">
            @foreach($files as $file)
                <li>{{ basename($file) }}</li>
            @endforeach
        </ul>
    @else
        <p>Nessun allegato.</p>
    @endif
</body>
</html>
