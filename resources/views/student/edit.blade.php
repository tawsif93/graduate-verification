@extends('layouts.app')

@section('content')
	<div class="container-fluid">
		<div class="row">

			<main class="col-sm-9 ml-sm-auto col-md-10 pt-3" role="main">
				<div class="row">
					<div class="col-lg-12 margin-tb">
						<div class="pull-left">
							<h2>Edit Student</h2>
						</div>
						<div class="pull-right">
							<a class="btn btn-primary" href="{{ url()->previous() }}"> Back</a>
						</div>
					</div>
				</div>

				@if (count($errors) > 0)
					<div class="alert alert-danger">
						<strong>Whoops!</strong> There were some problems with your input.<br><br>
						<ul>
							@foreach ($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
				@endif

				{!! Form::model($student, ['method' => 'POST','route' => ['student.update', $student->id]]) !!}
				<div class="row">

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Name:</strong>
							{!! Form::text('first_name', $user->first_name, array('placeholder' => 'First Name','class' => 'form-control')) !!}
						</div>
					</div>

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Last Name:</strong>
							{!! Form::text('last_name', $user->last_name, array('placeholder' => 'Last Name','class' => 'form-control')) !!}
						</div>
					</div>
				

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Session:</strong>
							{!! Form::text('session', null, array('placeholder' => 'Session','class' => 'form-control')) !!}
						</div>
					</div>

					<div class="col-xs-12 col-sm-12 col-md-12">
						<div class="form-group">
							<strong>Registration No:</strong>
							{!! Form::text('registration_no', null, array('placeholder' => 'Registration No.','class' => 'form-control')) !!}
						</div>
					</div>


					<div class="col-xs-12 col-sm-12 col-md-12 text-center">
						<button type="submit" class="btn btn-primary">Submit</button>
					</div>
					{{ Form::hidden('url',URL::previous()) }}

				</div>
				{!! Form::close() !!}
			</main>
		</div>
	</div>

@endsection
