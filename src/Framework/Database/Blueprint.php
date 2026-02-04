<?php

namespace Echo\Framework\Database;

class Blueprint
{
    private array $definitions = [];
    private array $alterDrops = [];
    private array $alterRenames = [];
    private bool $alterMode = false;

    public function last()
    {
        return count($this->definitions) - 1;
    }

    public function getDefinition(int $index)
    {
        return key_exists($index, $this->definitions)
            ? $this->definitions[$index]
            : null;
    }

    public function lastDefinition()
    {
        return $this->getDefinition($this->last());
    }

    public function appendDefinition(int $index, string $string)
    {
        $definition = $this->getDefinition($index);
        $this->definitions[$index] = "$definition $string";
        return $this;
    }

    public function insertDefinition(int $index, int $pos, string $string)
    {
        if (key_exists($index, $this->definitions)) {
            $definition = $this->definitions[$index];
            if ($pos <= strlen($definition)) {
                $this->definitions[$index] = substr_replace(
                    $definition,
                    $string,
                    $pos,
                    0
                );
            }
        }
        return $this;
    }

    /**
     * Add a default value to column
     * @param string $separator Definition separator
     */
    public function getDefinitions(string $separator = ", "): string
    {
        return implode($separator, $this->definitions);
    }

    /**
     * Add a default value to column

     * @param string $value Default value
     */
    public function default(string $value)
    {
        $this->appendDefinition($this->last(), "DEFAULT $value");
        return $this;
    }

    /**
     * Specify a column as unique index
     * @param string $attribute Unique attribute
     */
    public function unique(string $attribute)
    {
        $this->definitions[] = sprintf("UNIQUE KEY (%s)", $attribute);
        return $this;
    }

    /**
     * Specify a reference attribute for foreign key constraint
     * @param string $attribute Attribute of foreign key constraint
     */
    public function references(string $table_name, string $attribute)
    {
        $this->appendDefinition(
            $this->last(),
            "REFERENCES $table_name($attribute)"
        );
        return $this;
    }

    /**
     * Specify a ON DELETE properites of the constraint
     * @param string $action Action to take on delete
     */
    public function onDelete(string $action)
    {
        $this->appendDefinition($this->last(), "ON DELETE $action");
        return $this;
    }

    public function autoIncrement()
    {
        $this->appendDefinition($this->last(), "AUTO_INCREMENT");
        return $this;
    }

    /**
     * Specify a ON UPDATE properites of the constraint
     * @param string $action Action to take on delete
     */
    public function onUpdate(string $action)
    {
        $this->appendDefinition($this->last(), "ON UPDATE $action");
        return $this;
    }

    /**
     * Specify an index (compound or composite)
     * @param array $attributes Indexed attribute
     */
    public function index(array $attribute)
    {
        $this->definitions[] = sprintf("INDEX (%s)", $attribute);
        return $this;
    }

    /**
     * Allow NULL values to be inserted into the column
     */
    public function nullable()
    {
        $index = count($this->definitions) - 1;
        if (key_exists($index, $this->definitions)) {
            $definition = $this->definitions[$index];
            $this->definitions[$index] = str_replace(
                " NOT NULL",
                " NULL",
                $definition
            );
        }
        return $this;
    }

    /**
     * Specify a character set for the column
     * @param string $character_set Character set
     */
    public function charset(string $character_set = "utf8mb4")
    {
        return $this;
    }

    /**
     * Specify a collation for the column
     * @param string $collation Collation
     */
    public function collation(string $collation = "utf8mb4_unicode_ci")
    {
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED BIGINT (primary key) column
     * @param string $attribute Name of attribute
     */
    public function bigIncrements(string $attribute)
    {
        $this->unsignedBigInteger($attribute)->autoIncrement();
        return $this;
    }

    /**
     * Creates a BIGINT column
     * @param string $attribute Name of attribute
     */
    public function bigInteger(string $attribute)
    {

        $this->definitions[] = sprintf("%s BIGINT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a BINARY column
     * @param string $attribute Name of attribute
     * @param int $length Length of varchar
     */
    public function binary(string $attribute, int $length)
    {
        $this->definitions[] = sprintf(
            "%s BINARY(%s) NOT NULL",
            $attribute,
            $length
        );
        return $this;
    }

    /**
     * Creates a BLOB column

     * @param string $attribute Name of attribute
     */
    public function blob(string $attribute)
    {
        $this->definitions[] = sprintf("%s BLOB NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a TINYBLOB column
     * @param string $attribute Name of attribute

     */
    public function tinyBlob(string $attribute)
    {
        $this->definitions[] = sprintf("%s TINYBLOB NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a MEDIUMBLOB column
     * @param string $attribute Name of attribute
     */
    public function mediumBlob(string $attribute)
    {
        $this->definitions[] = sprintf("%s MEDIUMBLOB NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a LONGBLOB column
     * @param string $attribute Name of attribute
     */
    public function longBlob(string $attribute)
    {
        $this->definitions[] = sprintf("%s LONGBLOB NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a BOOLEAN column
     * @param string $attribute Name of attribute
     */
    public function boolean(string $attribute)
    {
        $this->definitions[] = sprintf("%s BOOLEAN NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a CHAR column
     * @param string $attribute Name of attribute
     * @param int $length Length of char
     */
    public function char(string $attribute, int $length)
    {
        $this->definitions[] = sprintf(
            "%s CHAR(%s) NOT NULL",
            $attribute,
            $length
        );
        return $this;
    }

    /**
     * Creates a DATETIME column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     */
    public function dateTime(string $attribute, int $precision = 0)
    {
        $this->definitions[] = sprintf(

            "%s DATETIME(%s) NOT NULL",
            $attribute,
            $precision
        );
        return $this;
    }

    /**
     * Creates a DATE column
     * @param string $attribute Name of attribute

     */
    public function date(string $attribute)
    {
        $this->definitions[] = sprintf("%s DATE NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a DECIMAL column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     * @param int $scale Decimal digits
     */
    public function decimal(string $attribute, $precision = 8, $scale = 2)
    {
        $this->definitions[] = sprintf(
            "%s DECIMAL(%s,%s) NOT NULL",
            $attribute,
            $precision,
            $scale
        );
        return $this;
    }

    /**
     * Creates a DOUBLE column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     * @param int $scale Decimal digits
     */
    public function double(string $attribute, $precision = 8, $scale = 2)
    {
        $this->definitions[] = sprintf(
            "%s DOUBLE(%s,%s) NOT NULL",
            $attribute,
            $precision,
            $scale
        );
        return $this;
    }

    /**
     * Creates an ENUM column
     * @param string $attribute Name of attribute
     * @param array $values Valid values of enum
     */
    public function enum(string $attribute, array $values)
    {
        $this->definitions[] = sprintf(
            "%s ENUM(%s) NOT NULL",
            $attribute,
            "'" . implode("','", $values) . "'"
        );
        return $this;
    }

    /**
     * Creates a FLOAT column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     * @param int $scale Decimal digits
     */
    public function float(string $attribute, int $precision = 8, int $scale = 2)
    {
        $this->definitions[] = sprintf(
            "%s FLOAT(%s,%s) NOT NULL",
            $attribute,
            $precision,
            $scale
        );
        return $this;
    }


    /**
     * Creates an primary key column
     * @param string $attribute Name of attribute(s)
     */
    public function primaryKey(string $attribute)
    {
        $this->definitions[] = sprintf("PRIMARY KEY (%s)", $attribute);
        return $this;
    }

    /**
     * Creates an foreign key column
     * @param string $attribute Name of attribute(s)
     */
    public function foreignKey(string $attribute)
    {
        $this->definitions[] = sprintf("FOREIGN KEY (%s)", $attribute);
        return $this;
    }

    /**
     * Creates a GEOMETRY column
     * @param string $attribute Name of attribute
     */
    public function geometry(string $attribute)
    {
        $this->definitions[] = sprintf("%s GEOMETRY", $attribute);
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED BIGINT (primary key) column
     * Alias of bigIncrements
     * @param string $attribute Name of attribute
     */
    public function id(string $attribute = "id")
    {
        $this->bigIncrements($attribute);
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED INTEGER column
     * @param string $attribute Name of attribute
     */
    public function increments(string $attribute)
    {
        $this->unsignedInteger($attribute)->autoIncrement();
        return $this;
    }

    /**
     * Add UNSIGNED attribute to column
     */
    public function unsigned()
    {
        $pos = strpos($this->lastDefinition(), "NOT NULL");
        if ($pos) {
            $this->insertDefinition($this->last(), $pos, "UNSIGNED ");
        }
        return $this;
    }

    /**
     * Creates an INTEGER column
     * @param string $attribute Name of attribute
     */
    public function integer(string $attribute)
    {
        $this->definitions[] = sprintf("%s INTEGER NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a JSON column
     * @param string $attribute Name of attribute
     */
    public function json(string $attribute)
    {
        $this->definitions[] = sprintf("%s JSON NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a JSONB column
     * @param string $attribute Name of attribute
     */
    public function jsonb(string $attribute)
    {
        $this->definitions[] = sprintf("%s JSONB NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED MEDIUMINT column
     * @param string $attribute Name of attribute
     */
    public function mediumIncrements(string $attribute)

    {
        $this->unsignedMediumInteger($attribute)->autoIncrement();
        return $this;
    }

    /**
     * Creates a MEDIUMINT column
     * @param string $attribute Name of attribute
     */
    public function mediumInteger(string $attribute)
    {
        $this->definitions[] = sprintf("%s MEDIUMINT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a MEDIUMTEXT column
     * @param string $attribute Name of attribute
     */
    public function mediumText(string $attribute)
    {
        $this->definitions[] = sprintf("%s MEDIUMTEXT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a MULTILINESTRING column
     * @param string $attribute Name of attribute
     */
    public function multiLineString(string $attribute)
    {
        return $this;
    }

    /**
     * Creates a MULTIPOINT column
     * @param string $attribute Name of attribute
     */
    public function multipoint(string $attribute)
    {
        return $this;
    }

    /**
     * Creates a MULTIPOLYGON column
     * @param string $attribute Name of attribute
     */
    public function multiPolygon(string $attribute)
    {
        return $this;
    }

    /**
     * Creates a POINT column
     * @param string $attribute Name of attribute
     */
    public function point(string $attribute)
    {
        $this->definitions[] = sprintf("%s POINT", $attribute);
        return $this;
    }

    /**
     * Creates a POLYGON column
     * @param string $attribute Name of attribute
     * @param array $values values of polygon
     */
    public function polygon(string $attribute, array $values)
    {
        $this->definitions[] = sprintf(
            "%s POLYGON(%s)",
            $attribute,
            implode(",", $values)
        );
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED SMALLINT column
     * @param string $attribute Name of attribute
     */
    public function smallIncrements(string $attribute)
    {
        $this->unsignedSmallInteger($attribute)->autoIncrement();
        return $this;
    }

    /**
     * Creates a SMALLINT column
     * @param string $attribute Name of attribute
     */
    public function smallInt(string $attribute)
    {
        $this->definitions[] = sprintf("%s SMALLINT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a VARCHAR column
     * @param string $attribute Name of attribute
     * @param int $length Length of varchar
     */
    public function varchar(string $attribute, int $length = 255)
    {
        $this->definitions[] = sprintf(
            "%s VARCHAR(%s) NOT NULL",
            $attribute,
            $length
        );
        return $this;
    }

    /**
     * Creates a TEXT column
     * @param string $attribute Name of attribute
     */
    public function text(string $attribute)
    {
        $this->definitions[] = sprintf("%s TEXT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a LONGTEXT column
     * @param string $attribute Name of attribute
     */
    public function longText(string $attribute)
    {
        $this->definitions[] = sprintf("%s LONGTEXT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a TIME column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     */
    public function time(string $attribute, int $precision)
    {
        $this->definitions[] = sprintf(
            "%s TIME(%s) NOT NULL",
            $attribute,
            $precision
        );
        return $this;
    }

    /**
     * Creates a TIMESTAMP column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     */
    public function timestamp(string $attribute, int $precision = 0)
    {
        $this->definitions[] = sprintf(
            "%s TIMESTAMP(%s) NOT NULL",
            $attribute,
            $precision
        );
        return $this;
    }

    /**
     * Creates created_at and updated_at columns
     * @param int $precision Total digits
     */
    public function timestamps(int $precision = 0)
    {
        $this->datetime("created_at")->default("CURRENT_TIMESTAMP");
        $this->timestamp("updated_at")
            ->nullable()
            ->onUpdate("CURRENT_TIMESTAMP");
        return $this;
    }

    /**
     * Creates an auto-incrementing UNSIGNED TINYINT column
     * @param string $attribute Name of attribute
     */
    public function tinyIncrements(string $attribute)
    {
        $this->unsignedTinyInteger($attribute)->autoIncrement();
        return $this;
    }

    /**
     * Creates a TINYINT column
     * @param string $attribute Name of attribute
     */
    public function tinyInteger(string $attribute)
    {
        $this->definitions[] = sprintf("%s TINYINT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates a TINYTEXT column
     * @param string $attribute Name of attribute
     */
    public function tinyText(string $attribute)
    {
        $this->definitions[] = sprintf("%s TINYTEXT NOT NULL", $attribute);
        return $this;
    }

    /**
     * Creates an UNSIGNED BIGINT column
     * @param string $attribute Name of attribute
     */
    public function unsignedBigInteger(string $attribute)
    {
        $this->bigInteger($attribute)->unsigned();
        return $this;
    }

    /**
     * Creates an UNSIGNED DECIMAL column
     * @param string $attribute Name of attribute
     * @param int $precision Total digits
     * @param int $scale Decimal digits
     */
    public function unsignedDecimal(
        string $attribute,
        int $precision,
        int $scale
    ) {
        return $this;
    }

    /**
     * Creates an UNSIGNED INTEGER column
     * @param string $attribute Name of attribute
     */
    public function unsignedInteger(string $attribute)
    {
        $this->integer($attribute)->unsigned();
        return $this;
    }

    /**
     * Creates an UNSIGNED MEDIUMINT column
     * @param string $attribute Name of attribute
     */
    public function unsignedMediumInteger(string $attribute)
    {
        $this->mediumInteger($attribute)->unsigned();
        return $this;
    }

    /**
     * Creates an UNSIGNED SMALLINT
     * @param string $attribute Name of attribute
     */
    public function unsignedSmallInteger(string $attribute)
    {
        $this->smallInt($attribute)->unsigned();
        return $this;
    }

    /**
     * Creates an UNSIGNED TINYINT
     * @param string $attribute Name of attribute
     */
    public function unsignedTinyInteger(string $attribute)
    {
        $this->tinyInteger($attribute)->unsigned();
        return $this;
    }

    /**
     * Creates a UUID column
     * @param string $attribute Name of attribute
     */
    public function uuid(string $attribute)
    {
        $this->char($attribute, 36);
        return $this;
    }

    /**
     * Creates a YEAR column
     * @param string $attribute Name of attribute
     */
    public function year(string $attribute)
    {
        $this->definitions[] = sprintf("%s YEAR NOT NULL", $attribute);
        return $this;
    }

    /**
     * Set alter mode for table modifications
     */
    public function setAlterMode(bool $mode): void
    {
        $this->alterMode = $mode;
    }

    /**
     * Drop a column from the table
     * @param string $column Column name to drop
     */
    public function dropColumn(string $column)
    {
        $this->alterDrops[] = $column;
        return $this;
    }

    /**
     * Rename a column
     * @param string $from Original column name
     * @param string $to New column name
     */
    public function renameColumn(string $from, string $to)
    {
        $this->alterRenames[] = ['from' => $from, 'to' => $to];
        return $this;
    }

    /**
     * Build ALTER TABLE statement
     * @param string $table Table name
     */
    public function buildAlter(string $table): string
    {
        $statements = [];

        // Add new columns
        foreach ($this->definitions as $def) {
            $statements[] = "ADD COLUMN $def";
        }

        // Drop columns
        foreach ($this->alterDrops as $col) {
            $statements[] = "DROP COLUMN $col";
        }

        // Rename columns
        foreach ($this->alterRenames as $rename) {
            $statements[] = "RENAME COLUMN {$rename['from']} TO {$rename['to']}";
        }

        if (empty($statements)) {
            return '';
        }

        return sprintf(
            "ALTER TABLE %s %s",
            $table,
            implode(", ", $statements)
        );
    }
}
