@extends('layouts.app')

@section('content')

	<div class="container-fluid">
		<div class="row">

			<main class="col-sm-9 ml-sm-auto col-md-10 pt-3" role="main">

				<div class="row">
					<div class="col-lg-12 margin-tb">
						<div class="pull-left">
							<h2> Show Course</h2>
						</div>
						<div class="pull-right">
							<a class="btn btn-primary" href="{{ url()->previous() }}"> Back</a>
						</div>
					</div>
				</div>

				<div class="row">

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Name:</strong>
								{{ $course->name }}
						</div>
					</div>

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Code:</strong>
							{{ $course->code }}
						</div>
					</div>

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Credit:</strong>
							{{ $course->credit }}
						</div>
					</div>

				</div>
			</main>
		</div>
	</div>
@endsection