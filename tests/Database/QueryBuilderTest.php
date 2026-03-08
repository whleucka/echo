<?php declare(strict_types=1);

namespace Tests\Database;

use Echo\Framework\Database\QueryBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class QueryBuilderTest extends TestCase
{
    public function testSelectBasic()
    {
        $qb = QueryBuilder::select()
            ->from("users");
        $this->assertSame("SELECT * FROM users", $qb->getQuery());
    }

    public function testSelectWithColumns()
    {
        $qb = QueryBuilder::select(["id", "name", "email"])
            ->from("users");
        $this->assertSame("SELECT id, name, email FROM users", $qb->getQuery());
    }

    public function testSelectWithWhere()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->where(["email = ?"], "test@test.com")
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
        $qb = QueryBuilder::select()
            ->from("users")
            ->limit(10);
        $this->assertSame("SELECT * FROM users LIMIT 10", $qb->getQuery());
    }

    public function testSelectWithLimitAndOffset()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->limit(10)
            ->offset(20);
        $this->assertSame("SELECT * FROM users LIMIT 10 OFFSET 20", $qb->getQuery());
    }

    public function testSelectOffsetWithoutLimitIsIgnored()
    {
        $qb = QueryBuilder::select()
            ->from("users")
            ->offset(20);
        $this->assertSame("SELECT * FROM users", $qb->getQuery());
    }

    public function testInsert()
    {
        $qb = QueryBuilder::insert(["name" => "test", "email" => "a@b.com"])
            ->into("users")
            ->params(["test", "a@b.com"]);
        $this->assertSame("INSERT INTO users (name, email) VALUES (?, ?)", $qb->getQuery());
        $this->assertSame(["test", "a@b.com"], $qb->getQueryParams());
    }

    public function testUpdate()
    {
        $qb = QueryBuilder::update(["name" => "new"])
            ->table("users")
            ->where(["id = ?"])
            ->params(["new", 1]);
        $this->assertSame("UPDATE users SET name = ? WHERE id = ?", $qb->getQuery());
    }

    public function testUpdateWithOrWhere()
    {
        $qb = QueryBuilder::update(["status" => "inactive"])
            ->table("users")
            ->where(["(role = ?)"])
            ->orWhere(["(expired = ?)"])
            ->params(["inactive", "guest", 1]);
        $this->assertSame(
            "UPDATE users SET status = ? WHERE (role = ?) OR (expired = ?)",
            $qb->getQuery()
        );
    }

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
        // params() overwrites the value set by where()
        $this->assertSame([42], $qb->getQueryParams());
    }
}
