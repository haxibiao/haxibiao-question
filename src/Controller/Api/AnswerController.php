<?php
/*
 * @Author: your name
 * @Date: 2020-12-08 09:25:38
 * @LastEditTime: 2020-12-08 11:10:08
 * @LastEditors: your name
 * @Description: In User Settings Edit
 * @FilePath: /neihan.sites/packages/haxibiao/question/src/Controller/Api/AnswerController.php
 */

namespace Haxibiao\Question\Controllers\Api;

use App\Http\Controllers\Controller;
use Haxibiao\Question\Question;
use Illuminate\Http\Request;

class AnswerController extends Controller
{

    public function store(Request $request)
    {
        $inputs = $request->input();
        if (!isset($inputs['question_id']) || !isset($inputs['answer'])) {
            return failed_response('参数错误!');
        }

        $question = Question::find($inputs['question_id']);
        if (empty($question)) {
            return failed_response('题目不存在!');
        }
        $user = auth()->user();

        return successful_response(Question::answerQuestion($user, $question, $inputs['answer']),200);
    }
}
