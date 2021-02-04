<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;


use App\User;
use App\Category;
use App\Question;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuestionTest extends GraphQLTestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $question;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory([
            'api_token' => str_random(60),
            'account' => rand(10000000000, 99999999999),
        ])->create();

        $this->category = Category::factory()->create();
        $this->question = Question::factory()->create([
            'category_id'=>$this->category->id,
            'rank'      => 1
        ]);
        
    }

    
    /**
     * 用户出题
     * @group question
     * @group testCreateQuestion
     */
    protected function testCreateQuestion()
    {// 无  App\User::checkRules() 逻辑
        $mutation = file_get_contents(__DIR__ . '/gql/Question/CreateQuestionMutation.graphql');
        //$user->checkRules();
        $variables = [
            "data" => [
                'category_id' => $this->category->id,
                'description' => "下列的城市是否有湖南省的？",
                'selections'  => [["Text" => "yes", "Value" => "A"], ["Text" => "no", "Value" => "B"], ["Text" => "fuck", "Value" => "C"]],
                'answers'     => 'A',
            ],
        ];

        $this->startGraphQL($mutation, $variables, $this->getHeaders($this->user));
    }

    /**
     * 删除草稿箱题目
     *
     * @group question
     * @group testDeleteQuestion
     */
    public function testDeleteQuestion()
    {
        $mutation = file_get_contents(__DIR__ . '/gql/Question/DeleteQuestionMutation.graphql');

        $question = Question::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $variables = [
            'id' => $question->id,
        ];
        $this->startGraphQL($mutation, $variables, $this->getHeaders($this->user));
    }

    /**
     * 撤回题目
     *
     * @group question
     * @group testRemoveQuestion
     */
    public function testRemoveQuestion()
    {
        $mutation = file_get_contents(__DIR__ . '/gql/Question/RemoveQuestionMutation.graphql');

        $question = Question::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $variables = [
            'id' => $question->id,
        ];

        $this->startGraphQL($mutation, $variables, $this->getHeaders($this->user));
    }

    /**
     * 答题
     *
     * @group question
     * @group testAnswerMutation
     */
    public function testAnswerMutation()
    {
        $mutation  = file_get_contents(__DIR__ . '/gql/Question/QuestionAnswerMutation.graphql');
        $variables = [
            //问题表主键ID
            'id'     => $this->question->id,

            //答案
            'answer' => 'A',
        ];

        $this->startGraphQL($mutation, $variables, $this->getHeaders($this->user));
    }

    /**
     * 发布题目(精力不足出题后暂存的或者撤回的)
     * @group question
     * @group testPublishQuestion
     */
    protected function testPublishQuestion()
    { // 无  App\User::checkRules() 逻辑
        $question = Question::factory()->create([
            'submit'=>Question::CANCELLED_SUBMIT
        ]);
        $mutation = file_get_contents(__DIR__ . '/gql/Question/PublishQuestionMutation.graphql');
       
        $variables = [
            'id' => $question->id,
        ];

        $this->startGraphQL($mutation, $variables, $this->getHeaders($question->user));
    }

    /* --------------------------------------------------------------------- */
    /* ------------------------------ Query ----------------------------- */
    /* --------------------------------------------------------------------- */

    /**
     * 随机答题
     * @group question
     * @group testRandomQuestionQuery
     */
    public function testRandomQuestionQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/Question/RandomQuestionQuery.graphql');
        $this->startGraphQL($query, [], $this->getHeaders($this->user));
    }

    /**
     * 题目列表
     *
     * @group question
     * @group testQuestionListQuery
     */
    protected function testQuestionListQuery()
    {// 用到了user 的 last_category_id 字段
        $query = file_get_contents(__DIR__ . '/gql/Question/QuestionListQuery.graphql');
        Question::factory()->create([
            'category_id'=>$this->category->id,
            'rank'      => 2
        ]);
        $this->category->ranks = [1,2];
        $this->category->save();
        $variables = [
            'category_id' => $this->category->id,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * 题目查询
     *
     * @group question
     * @group testQuestionQuery
     */
    public function testQuestionQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/QuestionQuery.graphql');

        $variables = [
            //页数 不填默认值为10
            'id' => random_int(1, 5),
        ];

        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * 是否可答题
     *
     * @group question
     * @group testCanAnswer
     */
    // public function testCanAnswer()
    // {
    //     $query = file_get_contents(__DIR__ . '/gql/question/canAnswerQuery.gql');

    //     \App\Answer::create([
    //         'user_id' => $this->user->id,
    //         'question_id' => $this->question->id,
    //     ]);
    //     $variables = [];

    //     $this->runGuestGQL($query, $variables, $this->getHeaders($this->user));
    // }


    /**
     * 领取考试奖励
     *
     * @group question
     * @group testAnswerRewardMutation
     */
    public function testAnswerRewardMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/Question/TestAnswerRewardMutation.graphql');

        $variables = [
            'answers'     => [
                0 => [
                    'question_id' => $this->question->id,
                    'answer'      => 'B',
                ],
            ],
            "isWatchedAd" => false,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * 提交考试答案
     *
     * @group question
     * @group testAnswersMutation
     */
    public function testAnswersMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/Question/TestAnswersMutation.graphql');

        $variables = [

            'answers' => [

                0 => [
                    'question_id' => $this->question->id,
                    'answer'      => 'B',
                ]

            ],
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group audit
     */

    // 无 $user->can_audit  $user->audits()

    protected function testAuditMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/Audit/auditMutation.graphql');
        $variables = [
            'question_id' => $this->question->id,
            'status' => \random_int(1, 2) % 2 == 0 ? true : false,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    protected function tearDown(): void
    {
        $this->user->forceDelete();
        $this->question->forceDelete();
        parent::tearDown();
    }
}
