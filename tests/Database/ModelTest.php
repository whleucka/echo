<?php declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testQueryOperands()
    {
        // Test operands
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

    public function testQueryOrderBy()
    {
        // Order by
        $sql = User::where("first_name", "Will")->orderBy("first_name", "DESC")->sql();
        $this->assertSame("SELECT * FROM users WHERE (first_name = ?) ORDER BY first_name DESC", $sql["query"]);

        $sql = User::where("first_name", "Will")->orderBy("surname", "ASC")->sql();
        $this->assertSame("SELECT * FROM users WHERE (first_name = ?) ORDER BY surname ASC", $sql["query"]);
    }

    public function testQueryChains()
    {
        // Test chains
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
}
