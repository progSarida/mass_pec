<!DOCTYPE html>
<html>
<head>
    <style>

    </style>
</head>
<body>
    {{ $company->name }}<br>
    <br>
    <br>
    @php
        $tipo = $registry->is_email ? 'Posta Elettronica' : 'Posta Orinaria';
        $data = $registry->flow_type == \App\Enums\FlowType::ISSUED ? $registry->send_date : $registry->receive_date;
        switch($registry->flow_type){
            case \App\Enums\FlowType::ISSUED :
                break;
            case \App\Enums\FlowType::RECEIVED :
                break;
            default :
                break;
        }
        $mittente =
    @endphp
    {{ $tipo }} del {{ $data->format('d.m.Y') }} ({{ $data->format('H:m:s') }})<br>
    PROTOCOLLO n. {{ $registry->protocol_number }} del {{ $registry->created_at->format('d.m.Y') }} ({{ $registry->flow_type->getLabel() }})<br>
    <br>
    <br>
    <b>Mittente:</b> {}
</body>
</html>
