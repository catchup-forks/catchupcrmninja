<?php namespace App\Http\Middleware;

use Closure;
use Auth;
use Session;
use App\Models\Invitation;
use App\Models\Contact;
use App\Models\Organisation;

class Authenticate {
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next, $guard = 'user')
	{
		$authenticated = Auth::guard($guard)->check();
		
		if($guard == 'client' && !empty($request->invitation_key)){
			$old_key = session('invitation_key');
			if($old_key && $old_key != $request->invitation_key){
				if($this->getInvitationContactId($old_key) != $this->getInvitationContactId($request->invitation_key)){
					// This is a different client; reauthenticate
					$authenticated = false;
					Auth::guard($guard)->logout();
				}
			}					
			Session::put('invitation_key', $request->invitation_key);					
		}
		
		if($guard=='client'){
			$invitation_key = session('invitation_key');
			$organisation_id = $this->getInvitationAccountId($invitation_key);
			
			if(Auth::guard('user')->check() && Auth::user('user')->organisation_id === $organisation_id){
				// This is an admin; let them pretend to be a client
				$authenticated = true;
			}
			
			// Does this organisation require portal passwords?
			$organisation = Organisation::whereId($organisation_id)->first();
			if(!$organisation->enable_portal_password || !$organisation->isPro()){
				$authenticated = true;
			}
			
			if(!$authenticated){
				$contact = Contact::whereId($this->getInvitationContactId($invitation_key))->first();
				if($contact && !$contact->password){
					$authenticated = true;
				}
			}
		}
		
		if (!$authenticated)
		{
			if ($request->ajax())
			{
				return response('Unauthorized.', 401);
			}
			else
			{
				return redirect()->guest($guard=='client'?'/client/login':'/login');
			}
		}

		return $next($request);
	}
	
	protected function getInvitation($key){
		$invitation = Invitation::withTrashed()->where('invitation_key', '=', $key)->first();
		if ($invitation && !$invitation->is_deleted) {
			return $invitation;
		}
		else return null;
	}
	
	protected function getInvitationContactId($key){
		$invitation = $this->getInvitation($key);
		
		return $invitation?$invitation->contact_id:null;
	}
	
	protected function getInvitationAccountId($key){
		$invitation = $this->getInvitation($key);
		
		return $invitation?$invitation->organisation_id:null;
	}
}
