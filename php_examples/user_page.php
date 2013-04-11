<?php

/* 
    The following is a class from a project that was used in data virualization for the agriculture industry. This particular class was the controller that controlled what the client called the User page. It was an administrative page that they use to modify permissions and user-role levels for each of their particular users in the system. 

    Please note that some information has been stripped for propriety's sake. 

*/

class Users_Controller extends Base_Controller {
    //Controls the landing page for the customer 
    public function action_index()
    {
        $input  = Input::get('query');
        $input  = json_decode($input);
        $filters = isset($input->filters) ? $input->filters : [];

        //Grabs a the values from the filters on the left side of the page. 
        $u = new \Users\Portal\Users($filters);
        $reps = $u->reps();

        //returns a JSON response object (send_success forces it to respond with a success message; there are handlers in the JS to do something different when something goes wrong) and builds a page with the data provided above
        return JSONResponse::send_success('')
            ->with_view('portal::users.users',[
                'data'  => $u->users(),
                'reps'  => $reps,
            ])
            ->with_data(['reps' => $reps]);
    }

    //When the pagination is used on the page, this function grabs the next set of users to be displayed on the page via Javascript. 
    public function action_get_users() {
        $input  = Input::get('query');
        $input  = json_decode($input);

        $length = Input::get('iDisplayLength');
        $start  = Input::get('iDisplayStart');
        $echo   = Input::get('sEcho');

        $filters = isset($input->filters) ? $input->filters : [];

        $u = new \Users\Portal\Users($filters);
        $users = $u->users($start,$length);

        return json_encode(["sEcho" => $echo,"aaData" => $users['result'],"iTotalRecords"=>$users['count'],"iTotalDisplayRecords"=>$users['count']]);
    }


    /* Some helper actions omitted */

    /**
     *
     * If the user already has a rep, that relationship will be deleted,
     *
     * @return JSONResponse response object
     */

    //This method sets up a relationship between the user and another type of user within the system. It priovides error messages if the data doesn't validate on the back end. 
    public function action_set_grower_rep()
    {
        $input = Input::get();

        $validator = Validator::make($input, [
                'grower' => 'required|exists:users,id',
                'rep' => 'exists:users,id',
            ]);
        if ($validator->fails())
            return JSONResponse::send_error()->with_data($validator->errors);

        $grower = Grower::find($input['grower']);

        // make sure the grower and rep belong to the same company
        if ($input['rep']) {
            $rep = CorporateRep::find($input['rep']);

            if ($rep && $rep->companies_id != $grower->companies_id)
                return JSONResponse::send_error('The grower and rep must belong to the same company');
        }

        // Set the rep!
        $grower->corporate_rep = $input['rep'];

        return JSONResponse::send_success('Saved');
    }


    // On the page, the user (in this case a company) can edit stored information about their users. This is the controller method that allows them to control that information. When they submit the form that contains this information, this is called. 

    public function action_update()
    {
        //Gets information from the page 
        $input = Input::get();

        //Sends an immediate failure if the users' ID isn't set
        if(!isset($input['user_id'])) 
           return JSONResponse::send_error('Please enter a user ID');

        $user_id = $input['user_id'];

        //Look up that user from the database 
        $user = User::where_id($user_id);

        //validation rules 
        $rules = array(
            'user_id'          => 'required|numeric|user_id',
            'email_address'    => 'email',
            'level_id'         => 'integer',
        );

        //custom validation message
        $messages = array(
            'user_id' => 'You must choose a user that already exists in the database.',
        );

        //custom validation rule 
        Validator::register('user_id', function($attribute, $value, $parameters)
        {
            return User::where_id($value)->first();

        });

        $validator = Validator::make($input, $rules, $messages);

        //Validates the input: if there are errors in the data 
        if ($validator->fails()) {
            return JSONResponse::send_error('Errors')
                ->with_data( $validator->errors);

        } 
        // when the validator passes 
        // not all input on this page is required, so this separates out that information and saved only that which it needs to. 
        else {
            $user = User::find($input['user_id']);
            if (isset($input['email_address']))
                $user->email_address = $input['email_address'];

            if (isset($input['level_id'])) {
                // If the user is being switched to Full, email the user with account credentials and deactivate their account to force them to accept the TOS on login
                $full_id = AccessLevel::where_name('Full')->only('id');
                if ($user->access_levels_id != $full_id && $input['level_id'] == $full_id) {
                    $form = new \Forms\RegisterUser($user);
                    $user->authentication_code = $form->get_auth_token();
                    $form->password = User::generate_password();
                    $form->send_confirmation_email();

                    $user->password = Hash::make($form->password, 10);
                    $user->authenticated = false;
                    $user->active = false;
                }

                $user->access_levels_id = $input['level_id'];
            }

            if (isset($input['notes'])) {
                $associative = CompanyUser::where_users_id($input['user_id'])->first();
                if($associative) //there is already a note for this user
                {
                    $associative->notes = $input['notes'];
                    $save_state = $associative->save();
                    if (!$save_state)
                    {
                        return JSONResponse::send_error("There was an error saving your note. Please try again later.");
                    }
                }
                else // the user needs a new note created for them
                {
                    $associative = new CompanyUser();
                    $associative->companies_id = 6;
                    $associative->users_id = $input['user_id'];
                    $associative->notes = $input['notes'];
                    $save_state = $associative->save();

                    if (!$save_state)
                    {
                        return JSONResponse::send_error("There was an error saving your note. Please try again later.");
                    }
                }

            }

            if ($user->save())
                return JSONResponse::send_success("The validation passed successfully.");
            else
                return JSONResponse::send_error("There was an error saving your data. Please try again later.");
        }
    }

    /* method ommitted */ 

    //method to reset the user's password: 
    public function action_reset_password() {
        $users_id = Input::get('users_id');
        $user = User::find($users_id);

        //generate the form from the back end 
        $form = new \Forms\RegisterUser();

        //method tied to the user to do a hashing script on the inputted password
        $password = User::generate_password();

        //sent to the user to help verify that they have completed the form successfully. 
        $auth_token = $form->get_auth_token();

        $data = compact(['password', 'user', 'auth_token']);

        $user->active = false;
        $user->authenticated = false;
        $user->authentication_code = $auth_token;
        $user->password = Hash::make($password, **omitted**);
        $user->force_password_reset = true;
        $user->save();

        //send a new message to hte user
        PMail::send('portal::users.reset_password_email',$data,
            function($mail) use($user)
            {
                $mail->subject('**ommitted**')
                    ->to($user->email_address,$user->name);
            }
        );

        //send a message to be printed on the screen 
        return JSONResponse::send_success('An email has been sent to this user\'s email address');
    }

