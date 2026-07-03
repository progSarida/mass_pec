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
        $mittente = $email->account->address . " (";
        $mittente .= $email->account->public_name;
        $mittente .= ")";
        $destinatario = '';
        $recipients = $email->recipients;
        foreach($recipients as $recipient){
            if($destinatario !== '') $destinatario .= ', ';
            $destinatario .= $recipient .  " (" . \App\Models\Recipient::findByEmail($recipient)->description . ")";
        }
    @endphp
    <b class="intestazione-protocollo">Posta Elettronica (id {{ $email->id }})</b><br>
    <b class="intestazione-protocollo">Creata il {{ $email->created_at->format('d.m.Y') }} da {{ $email->createUser->name }}</b><br>
    <br>
    <br>
    @if($email->is_reply)
        $linkedRegistry = \App\Models\Registry::find($email->linked_registry_id);
        <div class="partecipanti">
            <b>In risposta a:</b> {{ $linkedRegistry->subject }} ({{ $linkedRegistry->protocol_number }})
        </div>
        <br>
    @endif
    @if($mittente !== 'self' && $destinatario !== 'self')
    <div class="partecipanti">
        <b>Mittente:</b> {{ $mittente }}<br>
        <b>Destinatari:</b> {{ $destinatario }}
    </div>
    <br>
    @endif
    <b class="titolo-sezione">OGGETTO</b><br>
    <div class="contenuto-testo">
        {!! $email->subject !!}
    </div>
    <br>
    <b class="titolo-sezione">TESTO</b>
    <br>
    <div class="contenuto-testo">
        {{-- {!! $email->eml_body ?? $email->body !!}<br> --}}
        {!!
            preg_replace(
                '/^(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)|(?:(?:\s|<br[^>]*>|<p>\s*<\/p>|&nbsp;)+)$/iu',
                '', $email->body
            )
        !!}
    </div>
    <br>
    @php
        use Illuminate\Support\Facades\Storage;

        $files = [];
        if ($email->attachment_path) {
            // Usa Storage::files proprio come fai nella risorsa Filament
            $files = Storage::files($email->attachment_path);
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
