<?php declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use Echo\Framework\Database\ModelNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    // ─── Query Operators ────────────────────────────────────────

    public function testQueryOperands()
    {
        $sql = User::where("email", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?)", $sql["query"]);

        $sql = User::where("email", "!=", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email != ?)", $sql["query"]);

        $sql = User::where("email", "<", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email < ?)", $sql["query"]);

        $sql = User::where("email", "<=", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email <= ?)", $sql["query"]);

        $sql = User::where("email", ">", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email > ?)", $sql["query"]);

        $sql = User::where("email", ">=", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email >= ?)", $sql["query"]);

        $sql = User::where("email", "IS", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email IS ?)", $sql["query"]);

        $sql = User::where("email", "NOT", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email NOT ?)", $sql["query"]);

        $sql = User::where("email", "LIKE", "%test@test.com%")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email LIKE ?)", $sql["query"]);
    }

    // ─── Order By ───────────────────────────────────────────────

    public function testQueryOrderBy()
    {
        $sql = User::where("first_name", "Will")->orderBy("first_name", "DESC")->sql();
        $this->assertSame("SELECT * FROM users WHERE (first_name = ?) ORDER BY first_name DESC", $sql["query"]);

        $sql = User::where("first_name", "Will")->orderBy("surname", "ASC")->sql();
        $this->assertSame("SELECT * FROM users WHERE (first_name = ?) ORDER BY surname ASC", $sql["query"]);
    }

    public function testMultipleOrderBy()
    {
        $sql = User::where("id", ">", "0")
            ->orderBy("role", "ASC")
            ->orderBy("first_name", "DESC")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) ORDER BY role ASC, first_name DESC",
            $sql["query"]
        );
    }

    public function testOrderByRaw()
    {
        $sql = User::where("id", ">", "0")
            ->orderByRaw("COALESCE(published_at, created_at) DESC")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) ORDER BY COALESCE(published_at, created_at) DESC",
            $sql["query"]
        );
    }

    public function testOrderByRawMixesWithOrderBy()
    {
        $sql = User::where("id", ">", "0")
            ->orderBy("role", "ASC")
            ->orderByRaw("RAND()")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) ORDER BY role ASC, RAND()",
            $sql["query"]
        );
    }

    public function testOrderByRawBindingsAppendedAfterWhere()
    {
        $sql = User::where("status", "active")
            ->orderByRaw("FIELD(role, ?, ?)", ["admin", "user"])
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (status = ?) ORDER BY FIELD(role, ?, ?)",
            $sql["query"]
        );
        $this->assertSame(["active", "admin", "user"], $sql["params"]);
    }

    // ─── Where Chains ───────────────────────────────────────────

    public function testQueryChains()
    {
        $sql = User::where("email", "test@test.com")->andWhere("first_name", "Will")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (first_name = ?)", $sql["query"]);

        $sql = User::where("email", "test@test.com")->andWhere("first_name", "Will")->andWhere("surname", "Hleucka")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (first_name = ?) AND (surname = ?)", $sql["query"]);

        $sql = User::where("email", "test@test.com")->andWhere("first_name", "Will")->orWhere("surname", "Hleucka")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (first_name = ?) OR (surname = ?)", $sql["query"]);
    }

    public function testWhereRaw()
    {
        $sql = User::where("email", "test@test.com")
            ->whereRaw("status IN (?, ?)", ['active', 'pending'])
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (status IN (?, ?))", $sql["query"]);
        $this->assertSame(["test@test.com", "active", "pending"], $sql["params"]);
    }

    public function testWhereBetween()
    {
        $sql = User::where("email", "test@test.com")
            ->whereBetween("created_at", "2024-01-01", "2024-12-31")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (created_at BETWEEN ? AND ?)", $sql["query"]);
        $this->assertSame(["test@test.com", "2024-01-01", "2024-12-31"], $sql["params"]);
    }

    public function testWhereNull()
    {
        $sql = User::where("email", "test@test.com")
            ->whereNull("deleted_at")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (deleted_at IS NULL)", $sql["query"]);
    }

    public function testWhereNotNull()
    {
        $sql = User::where("email", "test@test.com")
            ->whereNotNull("verified_at")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?) AND (verified_at IS NOT NULL)", $sql["query"]);
    }

    // ─── Where In ───────────────────────────────────────────────

    public function testWhereInStaticFactory()
    {
        $sql = User::whereIn("id", [1, 2, 3])->sql();
        $this->assertSame("SELECT * FROM users WHERE (id IN (?, ?, ?))", $sql["query"]);
        $this->assertSame([1, 2, 3], $sql["params"]);
    }

    // ─── query() factory ────────────────────────────────────────

    public function testQueryStartsUnfilteredChain()
    {
        $sql = User::query()->sql();
        $this->assertSame("SELECT * FROM users", $sql["query"]);
        $this->assertSame([], $sql["params"]);
    }

    public function testQueryChainsIntoSelectGroupOrder()
    {
        $sql = User::query()
            ->select(["role", "COUNT(*) as count"])
            ->groupBy("role")
            ->orderBy("count", "DESC")
            ->sql();
        $this->assertSame(
            "SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC",
            $sql["query"]
        );
        $this->assertSame([], $sql["params"]);
    }

    public function testQueryChainsIntoAndWhere()
    {
        $sql = User::query()->andWhere("role", "admin")->sql();
        $this->assertSame("SELECT * FROM users WHERE (role = ?)", $sql["query"]);
        $this->assertSame(["admin"], $sql["params"]);
    }

    // ─── with() is instance-only ────────────────────────────────
    //
    // Static with() was removed to prevent the Model::with('x')->where(...)
    // foot-gun (static where() would have dropped the eagerLoad). Chains now
    // start with query()/where()/whereIn()/find() and add with() later.

    public function testWithIsChainableAfterQuery()
    {
        $model = User::query();
        $this->assertSame($model, $model->with('avatar'));
    }

    public function testWithIsChainableAfterWhere()
    {
        $model = User::where('id', '1');
        $this->assertSame($model, $model->with('avatar'));
    }

    public function testLoadStillWorksAsAlias()
    {
        $model = User::query();
        $this->assertSame($model, $model->load('avatar'));
    }

    public function testStaticWithCallIsNoLongerAvailable()
    {
        // Demoted to instance-only; static call should fail with a PHP Error.
        $this->expectException(\Error::class);
        User::with('avatar');
    }

    public function testAndWhereInChained()
    {
        $sql = User::where("role", "admin")
            ->andWhereIn("id", [10, 20])
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (role = ?) AND (id IN (?, ?))", $sql["query"]);
        $this->assertSame(["admin", 10, 20], $sql["params"]);
    }

    public function testAndWhereNotInChained()
    {
        $sql = User::where("role", "admin")
            ->andWhereNotIn("id", [1, 2])
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (role = ?) AND (id NOT IN (?, ?))", $sql["query"]);
        $this->assertSame(["admin", 1, 2], $sql["params"]);
    }

    public function testWhereInEmptyArrayMatchesNothing()
    {
        $sql = User::whereIn("id", [])->sql();
        $this->assertSame("SELECT * FROM users WHERE (0 = 1)", $sql["query"]);
        $this->assertSame([], $sql["params"]);
    }

    public function testAndWhereNotInEmptyArrayMatchesEverything()
    {
        $sql = User::where("role", "admin")
            ->andWhereNotIn("id", [])
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (role = ?) AND (1 = 1)", $sql["query"]);
        $this->assertSame(["admin"], $sql["params"]);
    }

    public function testWhereInRejectsInvalidIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        User::whereIn("id; DROP TABLE users", [1, 2]);
    }

    public function testAndWhereInRejectsInvalidIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("role", "admin")->andWhereIn("col)--", [1]);
    }

    // ─── Group By ───────────────────────────────────────────────

    public function testGroupBy()
    {
        $sql = User::where("id", ">", "0")
            ->groupBy("role")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (id > ?) GROUP BY role", $sql["query"]);
    }

    public function testSelectWithGroupBy()
    {
        $sql = User::where("id", ">", "0")
            ->select(["role", "COUNT(*) as count"])
            ->groupBy("role")
            ->orderBy("count", "DESC")
            ->sql();
        $this->assertSame("SELECT role, COUNT(*) as count FROM users WHERE (id > ?) GROUP BY role ORDER BY count DESC", $sql["query"]);
    }

    public function testMultipleGroupBy()
    {
        $sql = User::where("id", ">", "0")
            ->groupBy("role", "status")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (id > ?) GROUP BY role, status", $sql["query"]);
    }

    public function testGroupByRaw()
    {
        $sql = User::where("id", ">", "0")
            ->groupByRaw("YEAR(created_at)")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) GROUP BY YEAR(created_at)",
            $sql["query"]
        );
    }

    public function testGroupByRawMixesWithGroupBy()
    {
        $sql = User::where("id", ">", "0")
            ->groupBy("role")
            ->groupByRaw("YEAR(created_at)")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) GROUP BY role, YEAR(created_at)",
            $sql["query"]
        );
    }

    // ─── Having ─────────────────────────────────────────────────

    public function testHaving()
    {
        $sql = User::where("id", ">", "0")
            ->select(["role", "COUNT(*) as cnt"])
            ->groupBy("role")
            ->having(["COUNT(*) > ?"], 5)
            ->sql();
        $this->assertSame(
            "SELECT role, COUNT(*) as cnt FROM users WHERE (id > ?) GROUP BY role HAVING (COUNT(*) > ?)",
            $sql["query"]
        );
        $this->assertSame(["0", 5], $sql["params"]);
    }

    public function testHavingMultipleClauses()
    {
        $sql = User::where("id", ">", "0")
            ->groupBy("role")
            ->having(["COUNT(*) > ?", "SUM(score) >= ?"], 5, 100)
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) GROUP BY role HAVING (COUNT(*) > ?) AND (SUM(score) >= ?)",
            $sql["query"]
        );
        $this->assertSame(["0", 5, 100], $sql["params"]);
    }

    public function testHavingRaw()
    {
        $sql = User::where("id", ">", "0")
            ->groupBy("role")
            ->havingRaw("COUNT(*) > ?", [5])
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (id > ?) GROUP BY role HAVING (COUNT(*) > ?)",
            $sql["query"]
        );
        $this->assertSame(["0", 5], $sql["params"]);
    }

    public function testHavingParamsPositionedCorrectly()
    {
        // Chain order: where -> groupBy -> having -> orderByRaw
        // SQL order:   WHERE -> GROUP BY -> HAVING -> ORDER BY
        // Param order must match SQL order.
        $sql = User::where("status", "active")
            ->groupBy("role")
            ->having(["COUNT(*) > ?"], 5)
            ->orderByRaw("FIELD(role, ?)", ["admin"])
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (status = ?) GROUP BY role HAVING (COUNT(*) > ?) ORDER BY FIELD(role, ?)",
            $sql["query"]
        );
        $this->assertSame(["active", 5, "admin"], $sql["params"]);
    }

    // ─── Paginate (input validation) ────────────────────────────

    public function testPaginateRejectsZeroPerPage()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->paginate(0);
    }

    public function testPaginateRejectsNegativePerPage()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->paginate(-1);
    }

    public function testPaginateThrowsWithGroupBy()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("paginate() does not support groupBy()");
        User::where("id", ">", "0")
            ->groupBy("role")
            ->paginate(10);
    }

    public function testPaginateThrowsWithHaving()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("paginate() does not support groupBy()");
        User::where("id", ">", "0")
            ->havingRaw("COUNT(*) > 5")
            ->paginate(10);
    }

    // ─── Select Columns ─────────────────────────────────────────

    public function testSelectCustomColumns()
    {
        $sql = User::where("id", ">", "0")
            ->select(["id", "email"])
            ->sql();
        $this->assertSame("SELECT id, email FROM users WHERE (id > ?)", $sql["query"]);
    }

    // ─── SQL with Limit ─────────────────────────────────────────

    public function testSqlWithLimit()
    {
        $sql = User::where("role", "admin")->sql(10);
        $this->assertSame("SELECT * FROM users WHERE (role = ?) LIMIT 10", $sql["query"]);
    }

    public function testSqlWithZeroLimitIsNoLimit()
    {
        $sql = User::where("role", "admin")->sql(0);
        $this->assertSame("SELECT * FROM users WHERE (role = ?)", $sql["query"]);
    }

    // ─── Params ─────────────────────────────────────────────────

    public function testParamsArePreservedAcrossChain()
    {
        $sql = User::where("email", "a@b.com")
            ->andWhere("first_name", "!=", "test")
            ->orWhere("role", "admin")
            ->sql();
        $this->assertSame(["a@b.com", "test", "admin"], $sql["params"]);
    }

    public function testWhereNullHasNoParams()
    {
        $sql = User::where("role", "admin")->whereNull("deleted_at")->sql();
        $this->assertSame(["admin"], $sql["params"]);
    }

    // ─── Invalid Operator Falls Back to = ───────────────────────

    public function testInvalidOperatorDefaultsToEquals()
    {
        // When a non-valid operator is given, it's treated as the value
        $sql = User::where("email", "test@test.com")->sql();
        $this->assertSame("SELECT * FROM users WHERE (email = ?)", $sql["query"]);
        $this->assertSame(["test@test.com"], $sql["params"]);
    }

    // ─── Model Metadata ─────────────────────────────────────────

    public function testGetTableName()
    {
        $model = User::where("id", "1");
        $this->assertSame("users", $model->getTableName());
    }

    public function testGetForeignKey()
    {
        $model = User::where("id", "1");
        $this->assertSame("user_id", $model->getForeignKey());
    }

    public function testGetIdReturnsNullForNewModel()
    {
        $model = User::where("id", "1");
        $this->assertNull($model->getId());
    }

    // ─── Magic Properties ───────────────────────────────────────

    public function testMagicSetAndGet()
    {
        $model = User::where("id", "1");
        $model->name = "Test";
        $this->assertSame("Test", $model->name);
    }

    public function testMagicGetReturnsNullForUndefined()
    {
        $model = User::where("id", "1");
        $this->assertNull($model->nonexistent);
    }

    public function testMagicIsset()
    {
        $model = User::where("id", "1");
        $model->email = "test@test.com";
        $this->assertTrue(isset($model->email));
        $this->assertFalse(isset($model->nonexistent));
    }

    // ─── getAttributes ──────────────────────────────────────────

    public function testGetAttributesIncludesSetValues()
    {
        $model = User::where("id", "1");
        $model->email = "test@test.com";
        $model->role = "admin";
        $attrs = $model->getAttributes();
        $this->assertSame("test@test.com", $attrs["email"]);
        $this->assertSame("admin", $attrs["role"]);
    }

    // ─── getRelation ────────────────────────────────────────────

    public function testGetRelationReturnsNullForUnloaded()
    {
        $model = User::where("id", "1");
        $this->assertNull($model->getRelation("posts"));
    }

    // ─── WhereRaw with empty params ─────────────────────────────

    public function testWhereRawWithNoParams()
    {
        $sql = User::where("role", "admin")
            ->whereRaw("status = 'active'")
            ->sql();
        $this->assertSame("SELECT * FROM users WHERE (role = ?) AND (status = 'active')", $sql["query"]);
        $this->assertSame(["admin"], $sql["params"]);
    }

    // ─── Combined clauses ───────────────────────────────────────

    public function testComplexQueryCombination()
    {
        $sql = User::where("role", "admin")
            ->andWhere("status", "active")
            ->whereNotNull("verified_at")
            ->orderBy("created_at", "DESC")
            ->select(["id", "email", "role"])
            ->sql(25);
        $this->assertSame(
            "SELECT id, email, role FROM users WHERE (role = ?) AND (status = ?) AND (verified_at IS NOT NULL) ORDER BY created_at DESC LIMIT 25",
            $sql["query"]
        );
        $this->assertSame(["admin", "active"], $sql["params"]);
    }

    // ─── Identifier Validation ──────────────────────────────────

    public function testWhereRejectsInvalidIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("email; DROP TABLE users", "test");
    }

    public function testOrWhereRejectsInvalidIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("email", "test")->orWhere("name' OR '1'='1", "x");
    }

    public function testAndWhereRejectsInvalidIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("email", "test")->andWhere("1=1; --", "x");
    }

    public function testOrderByRejectsInvalidDirection()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("email", "test")->orderBy("name", "SIDEWAYS");
    }

    public function testOrderByRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("email", "test")->orderBy("name; DROP TABLE users");
    }

    public function testGroupByRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->groupBy("role; --");
    }

    public function testWhereBetweenRejectsInvalidField()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->whereBetween("col' OR 1=1", "a", "b");
    }

    public function testWhereNullRejectsInvalidField()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->whereNull("col; DROP TABLE users");
    }

    public function testWhereNotNullRejectsInvalidField()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("id", ">", "0")->whereNotNull("col)--");
    }

    public function testValidIdentifiersAccepted()
    {
        $sql = User::where("email", "test")->sql();
        $this->assertStringContainsString("email", $sql["query"]);

        $sql = User::where("first_name", "test")->sql();
        $this->assertStringContainsString("first_name", $sql["query"]);

        $sql = User::where("users.email", "test")->sql();
        $this->assertStringContainsString("users.email", $sql["query"]);
    }

    public function testValidateIdentifierAllowsUnderscorePrefix()
    {
        $sql = User::where("_internal", "test")->sql();
        $this->assertStringContainsString("_internal", $sql["query"]);
    }

    public function testValidateIdentifierRejectsNumericPrefix()
    {
        $this->expectException(InvalidArgumentException::class);
        User::where("123col", "test");
    }

    // ─── latest() / oldest() ────────────────────────────────────

    public function testLatestDefaultsToCreatedAtDesc()
    {
        $sql = User::query()->latest()->sql();
        $this->assertSame("SELECT * FROM users ORDER BY created_at DESC", $sql["query"]);
    }

    public function testLatestAcceptsColumn()
    {
        $sql = User::query()->latest("updated_at")->sql();
        $this->assertSame("SELECT * FROM users ORDER BY updated_at DESC", $sql["query"]);
    }

    public function testOldestDefaultsToCreatedAtAsc()
    {
        $sql = User::query()->oldest()->sql();
        $this->assertSame("SELECT * FROM users ORDER BY created_at ASC", $sql["query"]);
    }

    public function testOldestAcceptsColumn()
    {
        $sql = User::where("role", "admin")->oldest("registered_at")->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?) ORDER BY registered_at ASC",
            $sql["query"]
        );
    }

    public function testLatestRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->latest("col; DROP TABLE users");
    }

    public function testOldestRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->oldest("col)--");
    }

    // ─── pluck / keyBy / value identifier validation ────────────

    public function testPluckRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->pluck("col; DROP TABLE users");
    }

    public function testKeyByRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->keyBy("col)--");
    }

    public function testValueRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->value("1=1; --");
    }

    // ─── findOrFail / firstOrFail ───────────────────────────────

    /**
     * Integration test: requires a working MySQL connection (id 99999999 must
     * not exist). Skipped on hosts without the pdo_mysql driver — host PHP
     * during local-only test runs without docker, for example.
     */
    #[RequiresPhpExtension('pdo_mysql')]
    public function testFindOrFailThrowsWhenNotFound()
    {
        try {
            User::findOrFail("99999999");
            $this->fail("Expected ModelNotFoundException");
        } catch (ModelNotFoundException $e) {
            $this->assertSame(User::class, $e->modelClass);
            $this->assertSame("99999999", $e->id);
            $this->assertStringContainsString("with id [99999999]", $e->getMessage());
        }
    }

    public function testModelNotFoundExceptionMessageFormat()
    {
        $withId = new ModelNotFoundException(User::class, "42");
        $this->assertSame(
            "No query results for model [App\\Models\\User] with id [42]",
            $withId->getMessage()
        );

        $withoutId = new ModelNotFoundException(User::class);
        $this->assertSame(
            "No query results for model [App\\Models\\User]",
            $withoutId->getMessage()
        );
        $this->assertNull($withoutId->id);
    }

    // ─── Date helpers ───────────────────────────────────────────

    public function testWhereDate()
    {
        $sql = User::where("status", "active")
            ->whereDate("created_at", "2026-05-22")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (status = ?) AND (DATE(created_at) = ?)",
            $sql["query"]
        );
        $this->assertSame(["active", "2026-05-22"], $sql["params"]);
    }

    public function testWhereDateWithOperator()
    {
        $sql = User::query()
            ->whereDate("created_at", ">=", "2026-01-01")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (DATE(created_at) >= ?)",
            $sql["query"]
        );
        $this->assertSame(["2026-01-01"], $sql["params"]);
    }

    public function testWhereYear()
    {
        $sql = User::query()->whereYear("created_at", 2026)->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (YEAR(created_at) = ?)",
            $sql["query"]
        );
        $this->assertSame([2026], $sql["params"]);
    }

    public function testWhereMonth()
    {
        $sql = User::query()->whereMonth("created_at", 5)->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (MONTH(created_at) = ?)",
            $sql["query"]
        );
        $this->assertSame([5], $sql["params"]);
    }

    public function testWhereDay()
    {
        $sql = User::query()->whereDay("created_at", 22)->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (DAY(created_at) = ?)",
            $sql["query"]
        );
    }

    public function testWhereTime()
    {
        $sql = User::query()->whereTime("created_at", ">", "12:00:00")->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (TIME(created_at) > ?)",
            $sql["query"]
        );
        $this->assertSame(["12:00:00"], $sql["params"]);
    }

    public function testWhereNotBetween()
    {
        $sql = User::where("active", "1")
            ->whereNotBetween("created_at", "2026-01-01", "2026-12-31")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (active = ?) AND (created_at NOT BETWEEN ? AND ?)",
            $sql["query"]
        );
        $this->assertSame(["1", "2026-01-01", "2026-12-31"], $sql["params"]);
    }

    public function testWhereDateRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->whereDate("col; DROP TABLE users", "2026-01-01");
    }

    public function testWhereYearRejectsInvalidColumn()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->whereYear("1=1; --", 2026);
    }

    public function testWhereNotBetweenRejectsInvalidField()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->whereNotBetween("col)--", "a", "b");
    }

    // ─── when / unless ──────────────────────────────────────────

    public function testWhenAppliesCallbackWhenTruthy()
    {
        $sql = User::query()
            ->when("admin", fn($q, $v) => $q->andWhere("role", $v))
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?)",
            $sql["query"]
        );
        $this->assertSame(["admin"], $sql["params"]);
    }

    public function testWhenSkipsCallbackWhenFalsy()
    {
        $sql = User::query()
            ->when(null, fn($q, $v) => $q->andWhere("role", $v))
            ->sql();
        $this->assertSame("SELECT * FROM users", $sql["query"]);
        $this->assertSame([], $sql["params"]);
    }

    public function testWhenAppliesDefaultWhenFalsy()
    {
        $sql = User::query()
            ->when(false,
                fn($q, $v) => $q->andWhere("role", "admin"),
                fn($q, $v) => $q->andWhere("role", "guest"),
            )
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?)",
            $sql["query"]
        );
        $this->assertSame(["guest"], $sql["params"]);
    }

    public function testWhenCallbackReturningNullStillChains()
    {
        // Callback mutates $this in-place and returns nothing — must still chain.
        $sql = User::query()
            ->when(true, function ($q, $v) { $q->andWhere("role", "admin"); })
            ->andWhere("status", "active")
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?) AND (status = ?)",
            $sql["query"]
        );
    }

    public function testUnlessIsInverseOfWhen()
    {
        $sql = User::query()
            ->unless(false, fn($q, $v) => $q->andWhere("role", "admin"))
            ->sql();
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?)",
            $sql["query"]
        );
    }

    // ─── firstOrCreate / updateOrCreate validation ──────────────

    public function testFirstOrCreateRejectsEmptyFind()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("firstOrCreate(): \$find must contain at least one column => value pair");
        User::firstOrCreate([]);
    }

    public function testUpdateOrCreateRejectsEmptyFind()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("updateOrCreate(): \$find must contain at least one column => value pair");
        User::updateOrCreate([]);
    }

    // ─── chunk validation ───────────────────────────────────────

    public function testChunkRejectsZeroSize()
    {
        $this->expectException(InvalidArgumentException::class);
        User::query()->orderBy("id")->chunk(0, fn() => true);
    }

    public function testChunkRequiresOrderBy()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("chunk() requires an ORDER BY clause");
        User::query()->chunk(10, fn() => true);
    }

    public function testChunkRejectsGroupBy()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("chunk() does not support groupBy()");
        User::query()->orderBy("id")->groupBy("role")->chunk(10, fn() => true);
    }

    // ─── increment / decrement / touch identifier validation ───

    public function testIncrementRejectsInvalidColumn()
    {
        $user = User::where("id", "1");
        $this->expectException(InvalidArgumentException::class);
        $user->increment("col; DROP TABLE users");
    }

    public function testDecrementRejectsInvalidColumn()
    {
        $user = User::where("id", "1");
        $this->expectException(InvalidArgumentException::class);
        $user->decrement("col)--");
    }

    public function testIncrementReturnsFalseForUnpersistedModel()
    {
        // Model with no id (not persisted) can't safely UPDATE — must no-op.
        $user = User::query();
        $this->assertNull($user->getId());
        $this->assertFalse($user->increment("view_count"));
    }

    public function testTouchReturnsFalseForUnpersistedModel()
    {
        $user = User::query();
        $this->assertFalse($user->touch());
    }

    // ─── Change tracking ────────────────────────────────────────

    public function testNewlySetAttributesAreDirty()
    {
        $user = User::query();
        $this->assertFalse($user->isDirty());
        $user->email = "x@y.com";
        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->isDirty("email"));
        $this->assertFalse($user->isClean());
        $this->assertFalse($user->isDirty("name"));
    }

    public function testGetDirtyReturnsOnlyChangedAttributes()
    {
        $user = User::query();
        $user->email = "a@b.com";
        $user->role = "admin";
        $dirty = $user->getDirty();
        $this->assertSame(["email" => "a@b.com", "role" => "admin"], $dirty);
    }

    public function testGetOriginalOnUnloadedModelReturnsNull()
    {
        $user = User::query();
        $user->email = "set@after.com";
        $this->assertNull($user->getOriginal("email"));
        $this->assertSame([], $user->getOriginal());
    }

    // ─── Serialization ──────────────────────────────────────────

    public function testToArrayReturnsAttributes()
    {
        $user = User::query();
        $user->email = "a@b.com";
        $user->role = "admin";
        $this->assertSame(
            ["email" => "a@b.com", "role" => "admin"],
            $user->toArray()
        );
    }

    public function testToJsonRoundTripsViaToArray()
    {
        $user = User::query();
        $user->email = "a@b.com";
        $json = $user->toJson();
        $this->assertSame('{"email":"a@b.com"}', $json);
    }

    public function testJsonEncodeUsesJsonSerialize()
    {
        $user = User::query();
        $user->role = "admin";
        // PHP's json_encode invokes jsonSerialize() automatically.
        $this->assertSame('{"role":"admin"}', json_encode($user));
    }

    public function testModelImplementsJsonSerializable()
    {
        $this->assertInstanceOf(\JsonSerializable::class, User::query());
    }

    public function testFreshReturnsNullForUnpersistedModel()
    {
        $user = User::query();
        $this->assertNull($user->fresh());
    }
}
