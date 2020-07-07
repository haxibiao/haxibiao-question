
<?php

//题库列表，题目列表，题目

use Illuminate\Support\Facades\Route;

Route::get('/questions', 'QuestionController@index');
Route::get('/questions/{category_id}', 'QuestionController@questions');
Route::get('/question/{id}', 'QuestionController@show');
Route::any('questions/{code}/qrcode', 'QuestionController@qrcode');


Route::post('/question/{id}/answer', 'QuestionController@getAnswer');
