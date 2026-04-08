<?php declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use InvalidArgumentException;
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
}
