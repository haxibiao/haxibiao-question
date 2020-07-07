
<?php


use Illuminate\Contracts\Routing\Registrar as RouteRegisterContract;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function (RouteRegisterContract $api) {
    //题目列表
    Route::any('questions', 'QuestionController@index');

    //题目详情
    Route::any('questions/{id}', 'QuestionController@show');
    //question
    Route::post('/question/hot', 'QuestionController@hotQuestion');
    Route::post('/question/import-question', 'QuestionController@importQuestion');
    Route::post('/question/import-explanation', 'QuestionController@importExplanation');
    // Route::post('/question/import-video', 'QuestionController@importVideo');
    // Route::post('/question/import-explanation-video', 'QuestionController@importExplanationVideo');
    // Route::get('/question/explanation', 'QuestionController@getExplantion');

    //加载更多题目列表
    Route::get('/questions/more', 'Api\QuestionController@more');
    Route::get('/questions/recommend', 'Api\QuestionController@recommend');



    //答题
    Route::post('answers', 'AnswerController@store');
});
