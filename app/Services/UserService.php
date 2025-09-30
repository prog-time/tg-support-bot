<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function createUser($name,$email,$password){
        $user= new User();
        $user->name=$name;
        $user->email=$email;
        $user->password=Hash::make($password);
        $user->save();
        return $user;
    }

    public function getUserByEmail( $email ){
        return User::where('email','=',$email)->first();
    }

    public function updateUserName($userId ,$newName){
        $user = User::find($userId); if($user){ $user->name=$newName; $user->save(); }
        return $user;
    }

    public function deleteUser($userId){$user=User::find($userId);if($user){$user->delete();}}
}
