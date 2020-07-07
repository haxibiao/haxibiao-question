<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;


use App\User;
use Haxibiao\Question\Category;
use Haxibiao\Question\Question;

class QuestionTest extends GraphQLTestCase
{
    protected $user;

    protected $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        $this->question = factory(Question::class)->create([
            'user_id' => $this->user->id,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /* -------------------------------- Query ------------------------------ */
    /* --------------------------------------------------------------------- */

    /**
     * 专题分类
     *
     * @group question
     * @throws \Exception
     */
    public function testCategoriesQuery()
    {
        $query     = file_get_contents(__DIR__ . '/gql/question/CategoriesQuery.graphql');
        $variables = [
            //页数 不填默认值为10
            'count' => 10,

            //页码
            'page'  => random_int(0, 1),
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 题目列表
     *
     * @group question
     * @throws \Exception
     */
    public function testQuestionListQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/QuestionListQuery.gql');

        //获取分类表主键ID
        $categoriesId = [11, 12, 23]; //FIXME: 先简单随机个旧题库，确保UT基本功能正常
        $cate_id      = $categoriesId[random_int(0, 2)];

        $variables = [
            //分类表主键ID
            'category_id' => $cate_id,
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 可出题的题库（支持搜索）
     *
     * @group question
     * @throws \Exception
     */
    public function testSearchCategoriesQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/CategoriesCanSubmitQuery.graphql');

        $variables = [
            //页数 不填默认值为10
            'count'   => 10,

            //页码
            'page'    => random_int(0, 1),

            //搜索关键词
            'keyword' => random_str(10),
        ];

        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 题目查询
     *
     * @group question
     * @throws \Exception
     */
    public function testQuestionQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/QuestionQuery.graphql');

        $variables = [
            //页数 不填默认值为10
            'id' => random_int(1, 5),
        ];

        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 随机答题
     *
     * @group question
     */
    public function testRandomQuestionQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/RandomQuestionQuery.graphql');
        $this->runGuestGQL($query, [], self::getHeaders($this->user));
    }

    /* --------------------------------------------------------------------- */
    /* ------------------------------ Mutation ----------------------------- */
    /* --------------------------------------------------------------------- */

    /**
     * 答题
     *
     * @group question
     */
    public function testAnswerMutation()
    {
        $mutation  = file_get_contents(__DIR__ . '/gql/question/QuestionAnswerMutation.graphql');
        $variables = [
            //问题表主键ID
            'id'     => $this->question->id,

            //答案
            'answer' => 'A',
        ];

        $this->runGuestGQL($mutation, $variables, self::getHeaders($this->user));
    }

    /**
     * 用户出题
     *
     * @group question
     */
    public function testCreateQuestion()
    {
        $mutation = file_get_contents(__DIR__ . '/gql/question/CreateQuestionMutation.graphql');

        $categoryId = Category::query()->pluck('id')->take(1)->first();

        $variables = [
            "data" => [
                'category_id' => $categoryId,
                'description' => "下列的城市是否有湖南省的？",
                'selections'  => [["Text" => "yes", "Value" => "A"], ["Text" => "no", "Value" => "B"], ["Text" => "fuck", "Value" => "C"]],
                'answers'     => 'A',
                'image'       => file_get_contents(__DIR__ . '/gql/question/image'),
            ],
        ];

        $this->runGuestGQL($mutation, $variables, self::getHeaders($this->user));
    }

    /**
     * 删除草稿箱题目
     *
     * @group question
     */
    public function testDeleteQuestion()
    {
        $mutation = file_get_contents(__DIR__ . '/gql/question/DeleteQuestionMutation.graphql');

        $variables = [
            'id' => $this->question->id,
        ];

        $this->runGuestGQL($mutation, $variables, self::getHeaders($this->user));
    }

    /**
     * 撤回题目
     *
     * @group question
     */
    public function testRemoveQuestion()
    {
        $mutation = file_get_contents(__DIR__ . '/gql/question/RemoveQuestionMutation.graphql');

        $variables = [
            'id' => $this->question->id,
        ];

        $this->runGuestGQL($mutation, $variables, self::getHeaders($this->user));
    }

    /**
     * 发布题目(精力不足出题后暂存的或者撤回的)
     *
     * @group
     */
    public function testPublishQuestion()
    {
        $question = Question::where('submit', Question::CANCELLED_SUBMIT)->first();
        $mutation = file_get_contents(__DIR__ . '/gql/question/PublishQuestionMutation.graphql');

        $variables = [
            'id' => $question->id,
        ];

        $this->runGuestGQL($mutation, $variables, self::getHeaders($question->user));
    }

    /**
     * 标签详情
     *
     *
     * @group question
     * @throws \Exception
     */
    public function testTagQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/TagQuery.gql');

        $variables = [
            'id' => 1,
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 标签列表
     *
     *
     * @group question
     * @throws \Exception
     */
    public function testTagsQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/TagsQuery.gql');

        $variables = [];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 审题
     *
     * @group question
     * @throws \Exception
     */
    public function testAuditMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/auditMutation.gql');

        $user         = User::first();
        $user->ticket = 600;
        $user->save();
        $variables = [
            'status'      => false,
            'question_id' => 3,
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($user));
    }

    /**
     * 领取考试奖励
     *
     * @group question
     * @throws \Exception
     */
    public function testAnswerRewardMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/TestAnswerRewardMutation.gql');

        $variables = [

            'answers'     => [

                0 => [
                    'question_id' => 3,
                    'answer'      => 'B',
                ],
                1 => [
                    'question_id' => 4,
                    'answer'      => 'B',
                ],

            ],
            "isWatchedAd" => false,
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 提交考试答案
     *
     * @group question
     * @throws \Exception
     */
    public function testAnswersMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/question/TestAnswersMutation.gql');

        $variables = [

            'answers' => [

                0 => [
                    'question_id' => 3,
                    'answer'      => 'B',
                ],
                1 => [
                    'question_id' => 4,
                    'answer'      => 'B',
                ],

            ],
        ];
        $this->runGuestGQL($query, $variables, self::getHeaders($this->user));
    }

    /**
     * 用户请求头基本信息
     * PS：仅使用传入用户的请求头基本信息，在这里仅使用 UserFactory 创建的临时用户
     *
     * @param $user
     * @return array
     */
    protected static function getHeaders($user)
    {
        $token = $user->api_token;

        $headers = [
            'token'         => $token,
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];

        return $headers;
    }

    protected function tearDown(): void
    {
        $this->user->forceDelete();
        $this->question->forceDelete();
        parent::tearDown();
    }
}
