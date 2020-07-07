<?php

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
            return failed_response(500, '参数错误!');
        }

        $question = Question::find($inputs['question_id']);
        if (empty($question)) {
            return failed_response(500, '题目不存在!');
        }
        $user = auth()->user();

        return successful_response(200, Question::answerQuestion($user, $question, $inputs['answer']));
    }
}
