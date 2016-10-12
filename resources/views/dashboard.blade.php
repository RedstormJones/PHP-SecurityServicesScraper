@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Main Dashboard</div>
            </div>
        </div>
    </div>
</div>


@if (count($tools))

	@foreach($tools as $tool)

		<div class="container">
		    <div class="row">
        		<div class="col-md-8 col-md-offset-2">
		            <div class="panel panel-default">
        		        <div class="panel-heading">
							<a href= "{{ url('/'.$tool) }}">{{$tool}}</a>

							<!-- SHOW TOOL-SPECIFIC DASHBOARD DATA HERE -->

						</div>
		            </div>
        		</div>
		    </div>
		</div>

	@endforeach

@endif

@endsection
