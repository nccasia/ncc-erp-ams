<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Bạn <b>{{ $data['user_name'] }}</b> xác nhận <b>{{ $data['is_confirm'] }}</b> {{ $data['count'] }} tool <b>{{ $data['tool_name'] }}.</b></p>
        <p>{{ $data['reason'] }}.</p>
    </div>
</body>