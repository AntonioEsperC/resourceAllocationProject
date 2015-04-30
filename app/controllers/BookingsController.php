<?php

class BookingsController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
		$bookings = Booking::where('return_date','=','0000-00-00 00:00:00')->get();
		return View::make('admin.bookings.index', array('bookings' => $bookings));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}
	
	/**
	* Store a newly created booking
	* @return Response
	*/
	public function bookResurce(){
		$schedules 		= Input::get('schedules');
		$resource_id 	= Input::get('resource_id');
		$user_id		= Session::get('school_id');
		$message	= "";
		$waiting_msg	= "<h3>En lista de espera: <br></h3>";
		$booking_msg	= "<h3>Reservas hechas: <br></h3>";
		$waitings 		= "";
		$bookings 		= "";
		$resource 		= Resource::find($resource_id);
		$user 			= User::where('school_id','=',$user_id)->first();

		if( !isset($schedules) or is_null($schedules)){
			return "<h1>Error: ningún horario fue seleccionado<h1>";
		}
		foreach ($schedules as $key => $schedule) {
			$aux = explode("%", $schedule);	//$aux[0] = type, $aux[1] = date
			//if this schedule needs to be put in waiting list because of an actual booking
			if($aux[0] == "w"){
				//save it on waiting list
				$waitinglist = new Waitinglist();
				$waitinglist->resource_id	=	$resource_id;
				$waitinglist->user_id		=	$user_id;
				$waitinglist->start_date 	= 	$aux[1];
				$waitinglist->end_date		=	$aux[2];
				$waitinglist->save();
				$waitings .= "<h4>" . $aux[1] . " - " . $aux[2] . "</h4> <br>";
			}else{
				//save it on bookings
				$booking 	= new Booking();
				$booking->resource_id	=	$resource_id;
				$booking->user_id		=	$user_id;
				$booking->start_date 	=	$aux[1];
				$booking->end_date		=	$aux[2];
				$booking->save();
				$bookings .= "<h4>" . $aux[1] . " - " . $aux[2] . "</h4> <br>";
			}
		}
		$resourceName = $resource->name;
		$labName 	  = $resource->laboratory->name;
		$userName	  = "".$user->first_name;
		$alternativeEmail	=	"".$user->email2;
		$alternative  = $user->alternative;

		//send notification to first mail
		//Use of queue to avoid user delay experience
		Mail::send('student.bookingNotification', array('userName'=>$userName, 'resourceName'=>$resourceName, 'labName'=>$labName, 'bookings'=>$bookings, 'waitings'=>$waitings), function($msg) use($user) {
		    $msg->to($user->email1, $user->school_id)->subject('Notificación de reserva');
		});

		if($user->alternative == 1){
		//send notification to alternative mail
			Mail::send('student.bookingNotification', array('userName'=>$userName, 'resourceName'=>$resourceName, 'labName'=>$labName, 'bookings'=>$bookings, 'waitings'=>$waitings), function($msg) use($user) {
			    $msg->to($user->email2, $user->school_id)->subject('Notificación de reserva');
			});
		}

		return View::make('student.bookingSummary', ['bookings' => $bookings, 'waitings' => $waitings]);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//Reassign the resource to the next waiting user
		$booking = Booking::find($id);
		$waiting = Waitinglist::where('resource_id','=',$booking->resource_id)
		->where('start_date','=',$booking->start_date)
		->orderBy('created_at')->first();

		//check if there is a waiting user
		if(count($waiting) != 0 ){
			//reassign resource to next user

			$resource = Resource::find($booking->resource_id);
			$oldUser = User::where('school_id','=',$booking->user_id)->first();
			$booking->user_id = $waiting->user_id;
			$booking->save();
			$newUser = User::where('school_id','=',$booking->user_id)->first();
			$waiting->delete();

			//notify the user about booking cancelation 
			Mail::send('admin.email.bookingCancelNotification', array('userName'=>$oldUser->first_name, 'resourceName'=>$resource->name, 'labName'=>$resource->laboratory->name ), function($msg) use($oldUser) {
			    $msg->to($oldUser->email1, $oldUser->school_id)->subject('Notificación de cancelación');
			});
			if($oldUser->alternative == 1){
			//send notification to alternative mail
				Mail::send('admin.email.bookingCancelNotification', array('userName'=>$oldUser->first_name, 'resourceName'=>$resource->name, 'labName'=>$resource->laboratory->name ), function($msg) use($oldUser) {
				    $msg->to($oldUser->email2, $oldUser->school_id)->subject('Notificación de cancelación');
				});
			}
			
			//notify the new user about resource assignation 
			Mail::send('admin.email.bookingAssignationNotification', array('userName'=>$newUser->first_name, 'resourceName'=>$resource->name, 'labName'=>$resource->laboratory->name, 'start_date'=>$booking->start_date, 'end_date'=>$booking->end_date ), function($msg) use($newUser) {
			    $msg->to($newUser->email1, $newUser->school_id)->subject('Notificación de cancelación');
			});
			if($newUser->alternative == 1){
			//send notification to alternative mail
				Mail::send('admin.email.bookingAssignationNotification', array('userName'=>$newUser->first_name, 'resourceName'=>$resource->name, 'labName'=>$resource->laboratory->name, 'start_date'=>$booking->start_date, 'end_date'=>$booking->end_date ), function($msg) use($newUser) {
				    $msg->to($newUser->email2, $newUser->school_id)->subject('Notificación de cancelación');
				});
			}
			
		}else{
			//free the resource
			$booking->delete();
		}
		return Redirect::to('bookings');

	}

	public function close($id)
	{
		//When the resource is returned the booking must be closed
		$booking = Booking::find($id);
		$booking->return_date =  DB::raw('NOW()');
		$booking->save(); 
		return Redirect::to('bookings');
	}

}
