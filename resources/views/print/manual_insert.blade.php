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
        switch($element->flow_type){
            case \App\Enums\FlowType::ISSUED :
                $tipo = 'posta in uscita';
                $mittente = "Sarida s.r.l.";
                $destinatario = '';
                $receivers = $email->receivers ?? [];
                foreach($receivers as $receiver){
                    if($destinatario !== '') $destinatario .= ', ';
                    $destinatario .= $receiver .  " (" . \App\Models\Recipient::findByEmail($receiver)->description . ")";
                }
                $data = "inviata il " . $element->send_date->format('d.m.Y');
                break;
            case \App\Enums\FlowType::RECEIVED :
                $tipo = 'posta in entrata';
                $senders = $email->senders ?? [];
                $mittenti = '';
                foreach($senders as $sender){
                    if($mittenti !== '') $mittenti .= ', ';
                    $mittenti .= \App\Models\Recipient::find($sender)->description;
                }
                $data = "ricevuta il " . $element->receive_date->format('d.m.Y');
                break;
            default :
                $tipo = 'comunicazione interna';
                $data = "comunicata il " . $element->internal_date->format('d.m.Y');
                break;
        }
        $parties = $email->interested_parties ?? [];
        $parti = '';
        foreach($parties as $party){
            if($destinatario !== '') $destinatario .= ', ';
            $parti  .= \App\Models\Recipient::find($party)->description;
        }
    @endphp
    <b class="intestazione-protocollo">Inserimento manuale {{ $tipo }} (id {{ $element->id }}) {{ $data }}</b><br>
    <b class="intestazione-protocollo">Creato il {{ $element->created_at->format('d.m.Y') }} da {{ $element->createUser->name }}</b><br>
    <br>
    <br>
    @php

    @endphp
    @if(!empty($senders))
    <div class="partecipanti">
        <b>Mittenti:</b> {{ $mittenti }}<br>
        <b>Destinatario:</b> Sarida s.r.l.
    </div>
    <br>
    @endif
    @if(!empty($receivers))
    <div class="partecipanti">
        <b>Mittente:</b> Sarida s.r.l.<br>
        <b>Destinatari:</b> {{ $destinatari }}
    </div>
    <br>
    @endif
    @if(!empty($parties))
    <div class="partecipanti">
       <b>Parti interessate:</b> {{ $mittente }}<br>
    </div>
    <br>
    @endif
    <b class="titolo-sezione">OGGETTO</b><br>
    <div class="contenuto-testo">
        {!! $element->subject !!}
    </div>
    <br>
    <b class="titolo-sezione">TESTO</b>
    <br>
    <div class="contenuto-testo">
        {{-- {!! $element->eml_body ?? $element->body !!}<br> --}}
        {!!
            preg_replace(
                '/^(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)|(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)$/iu',
                '',
                $element->eml_body ?? $element->body
            )
        !!}
    </div>
    <br>
    @php
        use Illuminate\Support\Facades\Storage;

        $files = [];
        if ($element->attachment_path) {
            // Usa Storage::files proprio come fai nella risorsa Filament
            $files = Storage::files($element->attachment_path);
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
