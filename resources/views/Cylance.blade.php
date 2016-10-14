@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Cylance Dashboard</div>
            </div>
        </div>
    </div>
</div>


<div class="container">
    <div class="row">
        <div class="col-md-6 col-md-offset-0">
            <div class="panel panel-default">
                <div class="panel-heading">Unsafe Devices</div>

                @if (count($bad_devices))

					<table style="width: 100%;">

						<tr>
							<th>Device Name</th>
							<th>Last Known User</th>
							<th>Unsafe Files</th>
						</tr>

					@foreach($bad_devices as $bad_device)

						<tr>

							<td style="padding: 3px;"><a href="{{ url('/Cylance/'.$bad_device['device_id']) }}">{{$bad_device['device_name']}}</a></td>

							<td>{{$bad_device['last_users_text']}}</td>

							<td style="text-align: center;">{{$bad_device['files_unsafe']}}</td>

						</tr>

					@endforeach

					</table>

                @endif

            </div>
        </div>
    </div>
</div>









@endsection
