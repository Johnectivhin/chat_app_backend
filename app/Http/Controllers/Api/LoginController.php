<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required',
            'name' => 'required',
            'type' => 'required',
            'open_id' => 'required',
            'email' => 'email|max:50',
            'phone' => 'max:30',
        ]);

        if ($validator->fails()) {
            return ['code' => -1, 'data' => 'no valid data', 'msg' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $map = [
            'type' => $validated['type'],
            'open_id' => $validated['open_id'],
        ];

        $user_id = null; // Initialize $user_id outside of the transaction closure

        $result = DB::table('users')->select(
            'avatar',
            'name',
            'description',
            'type',
            'token',
            'access_token',
            'online'
        )->where($map)->first();

        if (empty($result)) {
            $validated['token'] = md5(uniqid() . rand(10000, 99999));
            $validated['created_at'] = Carbon::now();
            $validated['access_token'] = md5(uniqid() . rand(100000, 9999999));
            $validated['expire_date'] = Carbon::now()->addDays(30);

            // Insert data into the 'users' table using transactions
            try {
                DB::transaction(function () use ($validated, &$user_id) {
                    // Ensure you have values for all required fields
                    $validated['password'] = bcrypt('defaultpassword'); // Replace with your actual password handling logic

                    // Remove the 'id' field from the validated data
                    unset($validated['id']);

                    $user_id = DB::table('users')->insertGetId($validated);
                    // Additional database operations, if any
                });

                // Retrieve the user data after insertion
                $user_result = DB::table('users')->select(
                    'avatar',
                    'name',
                    'description',
                    'type',
                    'token',
                    'access_token',
                    'online'
                )->where('id', '=', $user_id)->first();

                return ['code' => 0, 'data' => $user_result, 'msg' => 'User has been created'];
            } catch (\Exception $e) {
                // Log or print the exception message for debugging
                return ['code' => -1, 'data' => "no data available", 'msg' => (string)$e];
            }
        } else {
            $access_token = md5(uniqid() . rand(100000, 9999999));
            $expire_date = Carbon::now()->addDays(30);
            DB::table("Users")->where($map)->update(
                [
                    "access_token" => $access_token,
                    "expire_date" => $expire_date
                ]
            );
            $result->access_token = $access_token;
            return ['code' => 0, 'data' => $result, 'msg' => 'User information updated'];
        }
    }
}
