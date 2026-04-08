<?php declare(strict_types=1);

namespace Tests\Database;

use Echo\Framework\Database\Expression;
use Echo\Framework\Database\QueryBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class QueryBuilderTest extends TestCase
{
    // ─── Basic SELECT ────────────────────────────────────────────

    public function testSelectBasic()
    {
        $qb = QueryBuilder::select()->from("users");
        $this->assertSame("SELECT * FROM users", $qb->getQuery());
    }

    public function testSelectWithColumns()
    {
        $qb = QueryBuilder::select(["id", "name", "email"])->from("users");
        $this->assertSame("SELECT id, name, email FROM users", $qb->getQuery());
    }

    public function testSelectWithWhere()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["email = ?"])
            ->params(["test@test.com"]);
        $this->assertSame("SELECT * FROM users WHERE email = ?", $qb->getQuery());
        $this->assertSame(["test@test.com"], $qb->getQueryParams());
    }

    public function testSelectWithWhereAndOrWhere()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["(email = ?)", "(name = ?)"])
            ->orWhere(["(role = ?)"])
            ->params(["a@b.com", "test", "admin"]);
        $this->assertSame(
            "SELECT * FROM users WHERE (email = ?) AND (name = ?) OR (role = ?)",
            $qb->getQuery()
        );
    }

    public function testSelectWithOrWhereOnly()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->orWhere(["(role = ?)", "(role = ?)"])
            ->params(["admin", "editor"]);
        $this->assertSame(
            "SELECT * FROM users WHERE (role = ?) OR (role = ?)",
            $qb->getQuery()
        );
    }

    public function testSelectWithGroupBy()
    {
        $qb = QueryBuilder::select(["role", "COUNT(*) as cnt"])
            ->from("users")
            ->groupBy(["role"]);
        $this->assertSame("SELECT role, COUNT(*) as cnt FROM users GROUP BY role", $qb->getQuery());
    }

    public function testSelectWithHaving()
    {
        $qb = QueryBuilder::select(["role", "COUNT(*) as cnt"])
            ->from("users")
            ->groupBy(["role"])
            ->having(["COUNT(*) > ?"], 5)
            ->params([5]);
        $this->assertSame(
            "SELECT role, COUNT(*) as cnt FROM users GROUP BY role HAVING COUNT(*) > ?",
            $qb->getQuery()
        );
    }

    public function testSelectWithOrderBy()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->orderBy(["name ASC", "id DESC"]);
        $this->assertSame("SELECT * FROM users ORDER BY name ASC, id DESC", $qb->getQuery());
    }

    public function testSelectWithLimit()
    {
        $qb = QueryBuilder::select()->from("users")->limit(10);
        $this->assertSame("SELECT * FROM users LIMIT 10", $qb->getQuery());
    }

    public function testSelectWithLimitAndOffset()
    {
        $qb = QueryBuilder::select()->from("users")->limit(10)->offset(20);
        $this->assertSame("SELECT * FROM users LIMIT 10 OFFSET 20", $qb->getQuery());
    }

    public function testSelectOffsetWithoutLimitIsIgnored()
    {
        $qb = QueryBuilder::select()->from("users")->offset(20);
        $this->assertSame("SELECT * FROM users", $qb->getQuery());
    }

    // ─── INSERT ──────────────────────────────────────────────────

    public function testInsert()
    {
        $qb = QueryBuilder::insert(["name" => "test", "email" => "a@b.com"])
            ->into("users");
        $this->assertSame("INSERT INTO users (name, email) VALUES (?, ?)", $qb->getQuery());
        $this->assertSame(["test", "a@b.com"], $qb->getQueryParams());
    }

    // ─── UPDATE ──────────────────────────────────────────────────

    public function testUpdate()
    {
        $qb = QueryBuilder::update(["name" => "new"])
            ->table("users")
            ->where(["id = ?"], 1);
        $this->assertSame("UPDATE users SET name = ? WHERE id = ?", $qb->getQuery());
        $this->assertSame(["new", 1], $qb->getQueryParams());
    }

    public function testUpdateWithOrWhere()
    {
        $qb = QueryBuilder::update(["status" => "inactive"])
            ->table("users")
            ->where(["(role = ?)"], "guest")
            ->orWhere(["(expired = ?)"], 1);
        $this->assertSame(
            "UPDATE users SET status = ? WHERE (role = ?) OR (expired = ?)",
            $qb->getQuery()
        );
        $this->assertSame(["inactive", "guest", 1], $qb->getQueryParams());
    }

    // ─── DELETE ──────────────────────────────────────────────────

    public function testDelete()
    {
        $qb = QueryBuilder::delete()
            ->from("users")
            ->where(["id = ?"], 1);
        $this->assertSame("DELETE FROM users WHERE id = ?", $qb->getQuery());
    }

    public function testDeleteWithOrWhere()
    {
        $qb = QueryBuilder::delete()
            ->from("sessions")
            ->where(["(expired = ?)"])
            ->orWhere(["(user_id IS NULL)"])
            ->params([1]);
        $this->assertSame(
            "DELETE FROM sessions WHERE (expired = ?) OR (user_id IS NULL)",
            $qb->getQuery()
        );
    }

    // ─── Mode & Inspection ───────────────────────────────────────

    public function testGetModeReturnsCorrectMode()
    {
        $this->assertSame("select", QueryBuilder::select()->from("t")->getMode());
        $this->assertSame("insert", QueryBuilder::insert(["a" => 1])->into("t")->getMode());
        $this->assertSame("update", QueryBuilder::update(["a" => 1])->table("t")->getMode());
        $this->assertSame("delete", QueryBuilder::delete()->from("t")->getMode());
    }

    public function testGetQueryWithoutModeThrows()
    {
        $this->expectException(RuntimeException::class);
        $qb = new QueryBuilder();
        $qb->getQuery();
    }

    public function testDump()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["id = ?"])
            ->params([1]);
        $dump = $qb->dump();
        $this->assertSame("SELECT * FROM users WHERE id = ?", $dump["query"]);
        $this->assertSame([1], $dump["params"]);
    }

    public function testParamsOverwritesPrevious()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["id = ?"], 999)
            ->params([42]);
        $this->assertSame([42], $qb->getQueryParams());
    }

    // ─── DISTINCT ────────────────────────────────────────────────

    public function testDistinct()
    {
        $qb = QueryBuilder::select(["email"])->distinct()->from("users");
        $this->assertSame("SELECT DISTINCT email FROM users", $qb->getQuery());
    }

    public function testDistinctWithWhere()
    {
        $qb = QueryBuilder::select(["role"])
            ->distinct()
            ->from("users")
            ->where(["active = ?"])
            ->params([1]);
        $this->assertSame("SELECT DISTINCT role FROM users WHERE active = ?", $qb->getQuery());
    }

    // ─── JOINs ───────────────────────────────────────────────────

    public function testInnerJoin()
    {
        $qb = QueryBuilder::select()
            ->from("orders")
            ->join("users u", "u.id = orders.user_id");
        $this->assertSame(
            "SELECT * FROM orders INNER JOIN users u ON u.id = orders.user_id",
            $qb->getQuery()
        );
    }

    public function testLeftJoin()
    {
        $qb = QueryBuilder::select()
            ->from("orders")
            ->leftJoin("users u", "u.id = orders.user_id");
        $this->assertSame(
            "SELECT * FROM orders LEFT JOIN users u ON u.id = orders.user_id",
            $qb->getQuery()
        );
    }

    public function testRightJoin()
    {
        $qb = QueryBuilder::select()
            ->from("orders")
            ->rightJoin("payments p", "p.order_id = orders.id");
        $this->assertSame(
            "SELECT * FROM orders RIGHT JOIN payments p ON p.order_id = orders.id",
            $qb->getQuery()
        );
    }

    public function testCrossJoin()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->crossJoin("settings");
        $this->assertSame("SELECT * FROM users CROSS JOIN settings", $qb->getQuery());
    }

    public function testMultipleJoins()
    {
        $qb = QueryBuilder::select(["o.id", "u.name", "p.amount"])
            ->from("orders o")
            ->join("users u", "u.id = o.user_id")
            ->leftJoin("payments p", "p.order_id = o.id");
        $this->assertSame(
            "SELECT o.id, u.name, p.amount FROM orders o INNER JOIN users u ON u.id = o.user_id LEFT JOIN payments p ON p.order_id = o.id",
            $qb->getQuery()
        );
    }

    public function testJoinRaw()
    {
        $qb = QueryBuilder::select()
            ->from("audits")
            ->joinRaw("LEFT JOIN users ON users.id = audits.user_id");
        $this->assertSame(
            "SELECT * FROM audits LEFT JOIN users ON users.id = audits.user_id",
            $qb->getQuery()
        );
    }

    public function testJoinInUpdate()
    {
        $qb = QueryBuilder::update(["orders.status" => "cancelled"])
            ->table("orders")
            ->join("users u", "u.id = orders.user_id")
            ->where(["u.banned = ?"], 1);
        $this->assertSame(
            "UPDATE orders INNER JOIN users u ON u.id = orders.user_id SET orders.status = ? WHERE u.banned = ?",
            $qb->getQuery()
        );
        $this->assertSame(["cancelled", 1], $qb->getQueryParams());
    }

    public function testJoinInDelete()
    {
        $qb = QueryBuilder::delete()
            ->from("orders")
            ->join("users u", "u.id = orders.user_id")
            ->where(["u.banned = ?"])
            ->params([1]);
        $this->assertSame(
            "DELETE FROM orders INNER JOIN users u ON u.id = orders.user_id WHERE u.banned = ?",
            $qb->getQuery()
        );
    }

    // ─── WHERE IN / NOT IN ───────────────────────────────────────

    public function testWhereIn()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereIn("status", ["active", "pending"]);
        $this->assertSame("SELECT * FROM users WHERE status IN (?, ?)", $qb->getQuery());
        $this->assertSame(["active", "pending"], $qb->getQueryParams());
    }

    public function testWhereNotIn()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereNotIn("role", ["banned", "suspended"]);
        $this->assertSame("SELECT * FROM users WHERE role NOT IN (?, ?)", $qb->getQuery());
        $this->assertSame(["banned", "suspended"], $qb->getQueryParams());
    }

    public function testWhereInEmpty()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereIn("id", []);
        $this->assertSame("SELECT * FROM users WHERE 0 = 1", $qb->getQuery());
    }

    public function testWhereNotInEmpty()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereNotIn("id", []);
        $this->assertSame("SELECT * FROM users WHERE 1 = 1", $qb->getQuery());
    }

    public function testWhereInWithSubquery()
    {
        $sub = QueryBuilder::select(["user_id"])->from("orders")->where(["total > ?"])->params([100]);
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereIn("id", $sub);
        $this->assertSame(
            "SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE total > ?)",
            $qb->getQuery()
        );
        $this->assertSame([100], $qb->getQueryParams());
    }

    public function testWhereNotInWithSubquery()
    {
        $sub = QueryBuilder::select(["user_id"])->from("banned_users");
        $qb = QueryBuilder::select()
            ->from("users")
            ->whereNotIn("id", $sub);
        $this->assertSame(
            "SELECT * FROM users WHERE id NOT IN (SELECT user_id FROM banned_users)",
            $qb->getQuery()
        );
    }

    public function testWhereInCombinedWithWhere()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["active = ?"])
            ->whereIn("role", ["admin", "editor"])
            ->params([1, "admin", "editor"]);
        $this->assertSame(
            "SELECT * FROM users WHERE active = ? AND role IN (?, ?)",
            $qb->getQuery()
        );
    }

    // ─── Raw Expressions ─────────────────────────────────────────

    public function testRawExpression()
    {
        $expr = QueryBuilder::raw("NOW()");
        $this->assertInstanceOf(Expression::class, $expr);
        $this->assertSame("NOW()", $expr->value);
        $this->assertSame("NOW()", (string) $expr);
    }

    public function testRawExpressionWithBindings()
    {
        $expr = QueryBuilder::raw("COALESCE(?, ?)", ["default", "fallback"]);
        $this->assertSame("COALESCE(?, ?)", $expr->value);
        $this->assertSame(["default", "fallback"], $expr->bindings);
    }

    public function testRawExpressionInSelect()
    {
        $qb = QueryBuilder::select([
            "id",
            QueryBuilder::raw("CONCAT(first_name, ' ', last_name) AS full_name"),
        ])->from("users");
        $this->assertSame(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM users",
            $qb->getQuery()
        );
    }

    public function testRawExpressionInInsert()
    {
        $qb = QueryBuilder::insert([
            "name" => "test",
            "created_at" => QueryBuilder::raw("NOW()"),
        ])->into("users");
        $this->assertSame(
            "INSERT INTO users (name, created_at) VALUES (?, NOW())",
            $qb->getQuery()
        );
        $this->assertSame(["test"], $qb->getQueryParams());
    }

    public function testRawExpressionInUpdate()
    {
        $qb = QueryBuilder::update([
            "views" => QueryBuilder::raw("views + 1"),
        ])->table("posts")->where(["id = ?"], 42);
        $this->assertSame(
            "UPDATE posts SET views = views + 1 WHERE id = ?",
            $qb->getQuery()
        );
        $this->assertSame([42], $qb->getQueryParams());
    }

    // ─── Subqueries ──────────────────────────────────────────────

    public function testSubqueryInSelect()
    {
        $sub = QueryBuilder::select(["COUNT(*)"])->from("orders")->where(["orders.user_id = users.id"]);
        $qb = QueryBuilder::select([
            "users.*",
            QueryBuilder::subquery($sub, "order_count"),
        ])->from("users");
        $this->assertSame(
            "SELECT users.*, (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) AS order_count FROM users",
            $qb->getQuery()
        );
    }

    // ─── Upsert ──────────────────────────────────────────────────

    public function testInsertOnDuplicateKeyUpdateWithColumnNames()
    {
        $qb = QueryBuilder::insert(["email" => "a@b.com", "name" => "Test", "login_count" => 1])
            ->into("users")
            ->onDuplicateKeyUpdate(["name", "login_count"]);
        $this->assertSame(
            "INSERT INTO users (email, name, login_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), login_count = VALUES(login_count)",
            $qb->getQuery()
        );
        $this->assertSame(["a@b.com", "Test", 1], $qb->getQueryParams());
    }

    public function testInsertOnDuplicateKeyUpdateWithExpression()
    {
        $qb = QueryBuilder::insert(["email" => "a@b.com", "login_count" => 1])
            ->into("users")
            ->onDuplicateKeyUpdate([
                "login_count" => QueryBuilder::raw("login_count + 1"),
            ]);
        $this->assertSame(
            "INSERT INTO users (email, login_count) VALUES (?, ?) ON DUPLICATE KEY UPDATE login_count = login_count + 1",
            $qb->getQuery()
        );
        $this->assertSame(["a@b.com", 1], $qb->getQueryParams());
    }

    public function testInsertOnDuplicateKeyUpdateWithValue()
    {
        $qb = QueryBuilder::insert(["email" => "a@b.com", "name" => "Test"])
            ->into("users")
            ->onDuplicateKeyUpdate(["name" => "Updated"]);
        $query = $qb->getQuery();
        $this->assertSame(
            "INSERT INTO users (email, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?",
            $query
        );
        $this->assertSame(["a@b.com", "Test", "Updated"], $qb->getQueryParams());
    }

    // ─── UNION ───────────────────────────────────────────────────

    public function testUnion()
    {
        $q1 = QueryBuilder::select(["name"])->from("users");
        $q2 = QueryBuilder::select(["name"])->from("admins");
        $q1->union($q2);
        $this->assertSame(
            "(SELECT name FROM users) UNION (SELECT name FROM admins)",
            $q1->getQuery()
        );
    }

    public function testUnionAll()
    {
        $q1 = QueryBuilder::select(["name"])->from("users");
        $q2 = QueryBuilder::select(["name"])->from("admins");
        $q1->unionAll($q2);
        $this->assertSame(
            "(SELECT name FROM users) UNION ALL (SELECT name FROM admins)",
            $q1->getQuery()
        );
    }

    public function testUnionWithOrderByAndLimit()
    {
        $q1 = QueryBuilder::select(["name"])->from("users");
        $q2 = QueryBuilder::select(["name"])->from("admins");
        $q1->union($q2)->orderBy(["name ASC"])->limit(10);
        $this->assertSame(
            "(SELECT name FROM users) UNION (SELECT name FROM admins) ORDER BY name ASC LIMIT 10",
            $q1->getQuery()
        );
    }

    public function testMultipleUnions()
    {
        $q1 = QueryBuilder::select(["name"])->from("users");
        $q2 = QueryBuilder::select(["name"])->from("admins");
        $q3 = QueryBuilder::select(["name"])->from("guests");
        $q1->union($q2)->unionAll($q3);
        $this->assertSame(
            "(SELECT name FROM users) UNION (SELECT name FROM admins) UNION ALL (SELECT name FROM guests)",
            $q1->getQuery()
        );
    }

    public function testUnionWithParams()
    {
        $q1 = QueryBuilder::select(["name"])->from("users")->where(["active = ?"])->params([1]);
        $q2 = QueryBuilder::select(["name"])->from("admins")->where(["active = ?"])->params([1]);
        $q1->union($q2);
        $q1->getQuery(); // triggers param merging
        $this->assertSame([1, 1], $q1->getQueryParams());
    }

    // ─── Multiple where() calls ─────────────────────────────────

    public function testMultipleWhereCalls()
    {
        $qb = QueryBuilder::select()->from("users")
            ->where(["active = ?"], 1)
            ->where(["role = ?"], 'admin');
        // Second where() overwrites the first clause, but params accumulate
        $this->assertSame("SELECT * FROM users WHERE role = ?", $qb->getQuery());
        $this->assertSame([1, 'admin'], $qb->getQueryParams());
    }

    public function testMultipleOrWhereCalls()
    {
        $qb = QueryBuilder::select()->from("users")
            ->where(["active = ?"], 1)
            ->orWhere(["role = ?"], 'admin')
            ->orWhere(["status = ?"], 'pending');
        // Second orWhere() overwrites the first
        $this->assertSame("SELECT * FROM users WHERE active = ? OR status = ?", $qb->getQuery());
    }

    // ─── Params edge cases ──────────────────────────────────────

    public function testParamsWithEmptyArray()
    {
        $qb = QueryBuilder::select()->from("users")->params([]);
        $this->assertSame([], $qb->getQueryParams());
    }

    public function testParamsAfterWhereVariadic()
    {
        // where() with variadic params, then explicit params()
        $qb = QueryBuilder::select()->from("users")
            ->where(["id = ?"], 5)
            ->params([99]);
        // params() overwrites
        $this->assertSame([99], $qb->getQueryParams());
    }

    // ─── Limit edge cases ───────────────────────────────────────

    public function testLimitZeroIsIgnored()
    {
        $qb = QueryBuilder::select()->from("users")->limit(0);
        $this->assertSame("SELECT * FROM users", $qb->getQuery());
    }

    // ─── Complex scenarios ──────────────────────────────────────

    public function testSelectWithAllClauses()
    {
        $qb = QueryBuilder::select(["role", "COUNT(*) as cnt"])
            ->from("users")
            ->where(["active = ?"], 1)
            ->groupBy(["role"])
            ->having(["cnt > 5"])
            ->orderBy(["cnt DESC"])
            ->limit(10)
            ->offset(20);
        $this->assertSame(
            "SELECT role, COUNT(*) as cnt FROM users WHERE active = ? GROUP BY role HAVING cnt > 5 ORDER BY cnt DESC LIMIT 10 OFFSET 20",
            $qb->getQuery()
        );
    }

    public function testDeleteWithMultipleConditions()
    {
        $qb = QueryBuilder::delete()->from("users")
            ->where(["active = ?", "role = ?"], 0, 'guest');
        $this->assertSame("DELETE FROM users WHERE active = ? AND role = ?", $qb->getQuery());
        $this->assertSame([0, 'guest'], $qb->getQueryParams());
    }

    public function testUpdateWithMultipleConditions()
    {
        $qb = QueryBuilder::update(["status" => "inactive"])
            ->table("users")
            ->where(["role = ?", "last_login < ?"], 'guest', '2024-01-01');
        $this->assertSame(
            "UPDATE users SET status = ? WHERE role = ? AND last_login < ?",
            $qb->getQuery()
        );
        $this->assertSame(["inactive", "guest", "2024-01-01"], $qb->getQueryParams());
    }
}
