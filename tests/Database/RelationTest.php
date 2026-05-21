<?php declare(strict_types=1);

namespace Tests\Database;

use App\Models\FileInfo;
use App\Models\User;
use Echo\Framework\Database\Model;
use Echo\Framework\Database\Relation;
use PHPUnit\Framework\TestCase;

class RelationTest extends TestCase
{
    // ─── Capture Mode ───────────────────────────────────────────
    //
    // captureRelation() lets the eager loader extract a Relation descriptor
    // from a relation method without firing a database query — relation
    // methods continue to declare their natural return type (?Model / array).

    public function testCaptureBelongsToFromUserAvatar()
    {
        $user = new User();
        $rel = Model::captureRelation(fn() => $user->avatar());

        $this->assertInstanceOf(Relation::class, $rel);
        $this->assertSame(Relation::BELONGS_TO, $rel->type);
        $this->assertSame(FileInfo::class, $rel->related);
        // avatar() overrides foreign key to "avatar"
        $this->assertSame("avatar", $rel->parentColumn);
        $this->assertSame("id", $rel->relatedColumn);
    }

    public function testCaptureHasOneDefaultKeys()
    {
        $post = new RelTestPost();
        $rel = Model::captureRelation(fn() => $post->author());

        $this->assertInstanceOf(Relation::class, $rel);
        $this->assertSame(Relation::HAS_ONE, $rel->type);
        $this->assertSame(RelTestAuthor::class, $rel->related);
        $this->assertSame("id", $rel->parentColumn);
        $this->assertSame("reltestpost_id", $rel->relatedColumn);
    }

    public function testCaptureHasManyDefaultKeys()
    {
        $post = new RelTestPost();
        $rel = Model::captureRelation(fn() => $post->comments());

        $this->assertSame(Relation::HAS_MANY, $rel->type);
        $this->assertSame(RelTestComment::class, $rel->related);
        $this->assertSame("id", $rel->parentColumn);
        $this->assertSame("reltestpost_id", $rel->relatedColumn);
    }

    public function testCaptureHasManyExplicitKeys()
    {
        $post = new RelTestPost();
        $rel = Model::captureRelation(fn() => $post->commentsCustom());

        $this->assertSame("custom_post_fk", $rel->relatedColumn);
        $this->assertSame("custom_local", $rel->parentColumn);
    }

    public function testCaptureBelongsToConventionalDefaults()
    {
        $comment = new RelTestComment();
        $rel = Model::captureRelation(fn() => $comment->post());

        $this->assertSame(Relation::BELONGS_TO, $rel->type);
        $this->assertSame(RelTestPost::class, $rel->related);
        // belongsTo defaults: parent.<related>_id = related.id
        $this->assertSame("reltestpost_id", $rel->parentColumn);
        $this->assertSame("id", $rel->relatedColumn);
    }

    public function testCaptureReturnsNullWhenCallbackTouchesNoRelation()
    {
        $rel = Model::captureRelation(fn() => null);
        $this->assertNull($rel);
    }

    // ─── Capture mode is transient ──────────────────────────────

    public function testCaptureModeDoesNotLeakOutsideCallback()
    {
        Model::captureRelation(fn() => (new RelTestPost())->author());

        // After capture exits, a normal call should resolve (returns null here
        // because the parent has no id — no DB query is needed).
        $post = new RelTestPost();
        $this->assertNull($post->author());
    }

    // ─── Direct Resolve (no DB needed for null-key case) ────────
    //
    // Without an id on the parent, hasOne/hasMany/belongsTo short-circuit and
    // return the empty value — no query fires. This lets us assert direct
    // calls keep their pre-Relation return semantics.

    public function testHasOneReturnsNullWhenParentKeyIsNull()
    {
        $post = new RelTestPost();
        $this->assertNull($post->author());
    }

    public function testHasManyReturnsEmptyArrayWhenParentKeyIsNull()
    {
        $post = new RelTestPost();
        $this->assertSame([], $post->comments());
    }

    public function testBelongsToReturnsNullWhenForeignKeyIsNull()
    {
        $comment = new RelTestComment();
        $this->assertNull($comment->post());
    }

    // ─── Relation descriptor object (pure value object) ─────────

    public function testRelationStoresFields()
    {
        $parent = new RelTestPost();
        $rel = new Relation(
            Relation::HAS_ONE,
            $parent,
            RelTestAuthor::class,
            'id',
            'reltestpost_id',
        );

        $this->assertSame(Relation::HAS_ONE, $rel->type);
        $this->assertSame($parent, $rel->parent);
        $this->assertSame(RelTestAuthor::class, $rel->related);
        $this->assertSame('id', $rel->parentColumn);
        $this->assertSame('reltestpost_id', $rel->relatedColumn);
        $this->assertFalse($rel->isLoaded());
    }

    public function testRelationSetResultsMarksLoaded()
    {
        $rel = new Relation(
            Relation::HAS_ONE,
            new RelTestPost(),
            RelTestAuthor::class,
            'id',
            'reltestpost_id',
        );

        $rel->setResults(null);
        $this->assertTrue($rel->isLoaded());
        $this->assertNull($rel->getResults());
    }

    public function testRelationGetResultsReturnsInjectedSingle()
    {
        $author = new RelTestAuthor();
        $author->name = "Will";

        $rel = new Relation(
            Relation::HAS_ONE,
            new RelTestPost(),
            RelTestAuthor::class,
            'id',
            'reltestpost_id',
        );
        $rel->setResults($author);

        $this->assertSame($author, $rel->getResults());
        // Subsequent calls don't re-query — same instance.
        $this->assertSame($author, $rel->getResults());
    }

    public function testRelationGetResultsReturnsInjectedCollection()
    {
        $a = new RelTestComment(); $a->body = "first";
        $b = new RelTestComment(); $b->body = "second";

        $rel = new Relation(
            Relation::HAS_MANY,
            new RelTestPost(),
            RelTestComment::class,
            'id',
            'reltestpost_id',
        );
        $rel->setResults([$a, $b]);

        $this->assertSame([$a, $b], $rel->getResults());
    }
}

// ─── Fixture Models ─────────────────────────────────────────────
// Defined here so the tests are self-contained and don't depend on
// whatever happens to exist in app/Models.

class RelTestPost extends Model
{
    protected string $tableName = "rel_test_posts";

    public function author(): ?RelTestAuthor
    {
        return $this->hasOne(RelTestAuthor::class);
    }

    public function comments(): array
    {
        return $this->hasMany(RelTestComment::class);
    }

    public function commentsCustom(): array
    {
        return $this->hasMany(RelTestComment::class, "custom_post_fk", "custom_local");
    }
}

class RelTestComment extends Model
{
    protected string $tableName = "rel_test_comments";

    public function post(): ?RelTestPost
    {
        return $this->belongsTo(RelTestPost::class);
    }
}

class RelTestAuthor extends Model
{
    protected string $tableName = "rel_test_authors";
}
