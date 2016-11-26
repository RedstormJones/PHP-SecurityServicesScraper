@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Cylance Device</div>

				@if ($device)

					<strong>Device Name: </strong> {{$device->device_name}}<br>
					<strong>IP Address: </strong> {{$device->ip_addresses_text}}<br>
					<strong>Last Logged On User: </strong> {{$device->last_users_text}}<br>
					<strong>Zone(s): </strong> {{$device->zones_text}}<br>
					<strong>OS Version: </strong> {{$device->os_versions_text}}<br>
					<br>
					<strong>Agent Version: </strong> {{$device->agent_version_text}}<br>
					<strong>Policy Name: </strong> {{$device->policy_name}}<br>
					<br>
					<strong>Files Quarantined: </strong> {{$device->files_quarantined}}<br>
					<strong>Files Unsafe: </strong> {{$device->files_unsafe}}<br>
					<strong>Files Abnormal: </strong> {{$device->files_abnormal}}<br>
					<strong>Files Waived: </strong> {{$device->files_waived}}<br>
					<strong>Total Files Analyzed: </strong> {{$device->files_analyzed}}<br>

				@else

					<strong>No data exists for this device</strong>

				@endif

            </div>
        </div>
    </div>
</div>



{{--
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
--}}








@endsection
