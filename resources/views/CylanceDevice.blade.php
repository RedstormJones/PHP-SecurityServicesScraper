@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Cylance Device</div>

				@if ($device)

					<div class="panel-body">
						{{$device}}
					</div>

				@endif

            </div>
        </div>
    </div>
</div>



<!--
<div class="container">
    <div class="row">
        <div class="col-md-4 col-md-offset-0">
            <div class="panel panel-default">
                <div class="panel-heading">Naughty Devices by Files Unsafe</div>

                @if (count($bad_devices))

                    @foreach($bad_devices as $device_name => $files_unsafe)

                        <div class="panel-body">
                            {{$device_name}} &nbsp {{$files_unsafe}}
                        </div>

                    @endforeach

                @endif


            </div>
        </div>
    </div>
</div>
-->








@endsection
