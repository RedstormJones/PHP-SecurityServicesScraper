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
        <div class="col-md-2 col-md-offset-0">
            <div class="panel panel-default">
                <div class="panel-heading">Unsafe Devices</div>

                @if (count($bad_devices))

					<table>

					@foreach($bad_devices as $bad_device)

						<tr>

							<td><a href="{{ url('/Cylance/'.$bad_device['device_id']) }}">{{$bad_device['device_name']}}</a></td>

							<td>{{$bad_device['files_unsafe']}}</td>

						</tr>

					@endforeach

					</table>

                @endif

            </div>
        </div>
    </div>
</div>









@endsection
