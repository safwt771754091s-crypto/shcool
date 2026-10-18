<?php

namespace Tests\Unit\Support;

use App\Models\Organization;
use App\Models\Support\KbArticle;
use App\Services\Support\KbRetriever;
use App\Support\Permission\RoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbRetrieverTest extends TestCase
{
    use RefreshDatabase;

    protected KbRetriever $retriever;

    protected Organization $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($this->school);
        $this->actingAsTenant($this->school);

        $this->retriever = app(KbRetriever::class);
    }

    protected function article(string $question, string $answer, array $keywords = [], string $category = 'general', bool $published = true): KbArticle
    {
        return KbArticle::create([
            'category' => $category,
            'question' => $question,
            'answer' => $answer,
            'keywords' => $keywords,
            'published' => $published,
        ]);
    }

    public function test_it_normalises_arabic_variants(): void
    {
        $this->assertSame('احمد', $this->retriever->normalise('أحمد'));
        $this->assertSame('علي', $this->retriever->normalise('على'));
        $this->assertSame('مدرسه', $this->retriever->normalise('مدرسة'));
    }

    public function test_it_retrieves_the_most_relevant_article(): void
    {
        $billing = $this->article('كيف أسدد الرسوم الدراسية؟', 'افتح قائمة الرسوم.', ['سداد', 'رسوم'], 'billing');
        $this->article('كيف أعيد تعيين كلمة المرور؟', 'استخدم رابط الاستعادة.', ['نسيت'], 'account');

        $results = $this->retriever->search('أريد سداد الرسوم', ['billing']);

        $this->assertNotEmpty($results);
        $this->assertSame($billing->id, $results[0]['id']);
    }

    public function test_it_ignores_unpublished_articles(): void
    {
        $this->article('سؤال مخفي', 'جواب مخفي', ['مخفي'], 'general', false);

        $this->assertSame([], $this->retriever->search('سؤال مخفي'));
    }

    public function test_it_respects_the_category_filter(): void
    {
        $this->article('كيف أسدد الرسوم؟', 'جواب.', ['سداد'], 'billing');

        $this->assertSame([], $this->retriever->search('كيف أسدد الرسوم', ['attendance']));
    }

    public function test_global_articles_are_visible_to_a_tenant(): void
    {
        app(\App\Support\Tenancy\TenantManager::class)->withoutTenancy(function (): void {
            KbArticle::create([
                'tenant_id' => null,
                'category' => 'general',
                'question' => 'سؤال عام على مستوى المنصة',
                'answer' => 'جواب عام.',
                'keywords' => ['عام', 'منصة'],
                'published' => true,
            ]);
        });

        $results = $this->retriever->search('سؤال عام على مستوى المنصة');

        $this->assertNotEmpty($results);
    }
}
