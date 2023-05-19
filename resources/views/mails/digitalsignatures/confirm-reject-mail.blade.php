<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Bạn <b>{{ $data['user_name'] }}</b> <b>{{ $data['is_confirm'] }}</b> {{ $data['signatures_count'] }} token thuế có seri là:  <b>{{ $data['seri'] }}.</b></p>
        <p>{{ $data['reason'] }}.</p>
    </div>
</body>