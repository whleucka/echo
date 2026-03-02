<?php

declare(strict_types=1);

namespace Tests\Admin;

use Echo\Framework\Admin\ModuleState;
use Echo\Framework\Admin\Schema\{
    ColumnDefinition,
    FilterDefinition,
    FilterLinkDefinition,
    PaginationConfig,
    TableSchema,
};
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ModuleStateTest extends TestCase
{
    private ModuleState $state;
    private TableSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new ModuleState('test_module');
        $this->schema = $this->buildTestSchema();
    }

    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }

    private function buildTestSchema(): TableSchema
    {
        return new TableSchema(
            table: 'users',
            primaryKey: 'id',
            columns: [
                new ColumnDefinition('id', 'ID', null, false, null, null, null),
                new ColumnDefinition('name', 'Name', 'CONCAT(first_name, " ", surname)', true, null, null, null),
                new ColumnDefinition('email', 'Email', null, true, null, null, null),
                new ColumnDefinition('role', 'Role', null, false, null, null, null),
            ],
            filters: [
                new FilterDefinition('role', 'role', 'Role', 'dropdown', [
                    ['value' => 'admin', 'label' => 'Admin'],
                    ['value' => 'user', 'label' => 'User'],
                ], null),
            ],
            filterLinks: [
                new FilterLinkDefinition('All', '1=1'),
                new FilterLinkDefinition('Admins', "role = 'admin'"),
            ],
            actions: [],
            rowActions: [],
            toolbarActions: [],
            joins: [],
            defaultOrderBy: 'id',
            defaultSort: 'DESC',
            dateColumn: 'created_at',
            pagination: new PaginationConfig(perPage: 15, perPageOptions: [15, 25, 50, 100]),
        );
    }

    // =========================================================================
    // hydrateFromQuery tests
    // =========================================================================

    public function testHydrateFromQueryWithEmptyArray(): void
    {
        $actions = $this->state->hydrateFromQuery([], $this->schema);

        $this->assertNull($actions['edit']);
        $this->assertNull($actions['show']);
        $this->assertSame(1, $this->state->getPage());
    }

    public function testHydrateFromQuerySetsPage(): void
    {
        $this->state->hydrateFromQuery(['page' => '3'], $this->schema);

        $this->assertSame(3, $this->state->getPage());
    }

    public function testHydrateFromQueryRejectsInvalidPage(): void
    {
        $this->state->hydrateFromQuery(['page' => '-1'], $this->schema);

        $this->assertSame(1, $this->state->getPage());
    }

    public function testHydrateFromQueryRejectsNonNumericPage(): void
    {
        $this->state->hydrateFromQuery(['page' => 'abc'], $this->schema);

        $this->assertSame(1, $this->state->getPage());
    }

    public function testHydrateFromQuerySetsPerPage(): void
    {
        $this->state->hydrateFromQuery(['pp' => '50'], $this->schema);

        $this->assertSame(50, $this->state->getPerPage(15));
    }

    public function testHydrateFromQueryRejectsInvalidPerPage(): void
    {
        $this->state->hydrateFromQuery(['pp' => '99'], $this->schema);

        // Should remain at default since 99 is not in perPageOptions
        $this->assertSame(15, $this->state->getPerPage(15));
    }

    public function testHydrateFromQuerySetsSortColumn(): void
    {
        $this->state->hydrateFromQuery(['sort' => 'email'], $this->schema);

        $this->assertSame('email', $this->state->getOrderBy('id'));
    }

    public function testHydrateFromQueryRejectsUnknownSortColumn(): void
    {
        $this->state->hydrateFromQuery(['sort' => 'nonexistent'], $this->schema);

        $this->assertSame('id', $this->state->getOrderBy('id'));
    }

    public function testHydrateFromQuerySetsSortDirection(): void
    {
        $this->state->hydrateFromQuery(['dir' => 'asc'], $this->schema);

        $this->assertSame('ASC', $this->state->getSort('DESC'));
    }

    public function testHydrateFromQueryRejectsInvalidSortDirection(): void
    {
        $this->state->hydrateFromQuery(['dir' => 'sideways'], $this->schema);

        $this->assertSame('DESC', $this->state->getSort('DESC'));
    }

    public function testHydrateFromQuerySetsFilterLink(): void
    {
        $this->state->hydrateFromQuery(['fl' => '1'], $this->schema);

        $this->assertSame(1, $this->state->getActiveFilterLink());
    }

    public function testHydrateFromQueryRejectsOutOfRangeFilterLink(): void
    {
        $this->state->hydrateFromQuery(['fl' => '99'], $this->schema);

        $this->assertSame(0, $this->state->getActiveFilterLink());
    }

    public function testHydrateFromQuerySetsSearch(): void
    {
        $this->state->hydrateFromQuery(['search' => 'john'], $this->schema);

        $this->assertSame('john', $this->state->getFilter('search'));
    }

    public function testHydrateFromQueryClearsEmptySearch(): void
    {
        $this->state->setFilter('search', 'old');
        $this->state->hydrateFromQuery(['search' => ''], $this->schema);

        $this->assertNull($this->state->getFilter('search'));
    }

    public function testHydrateFromQuerySetsDateRange(): void
    {
        $this->state->hydrateFromQuery([
            'ds' => '2026-01-01',
            'de' => '2026-03-01',
        ], $this->schema);

        $this->assertSame('2026-01-01', $this->state->getFilter('date_start'));
        $this->assertSame('2026-03-01', $this->state->getFilter('date_end'));
    }

    public function testHydrateFromQuerySetsDropdownFilter(): void
    {
        $this->state->hydrateFromQuery(['f' => ['0' => 'admin']], $this->schema);

        $this->assertSame('admin', $this->state->getFilter('dropdowns_0'));
    }

    public function testHydrateFromQueryRejectsOutOfRangeDropdownFilter(): void
    {
        $this->state->hydrateFromQuery(['f' => ['5' => 'admin']], $this->schema);

        $this->assertNull($this->state->getFilter('dropdowns_5'));
    }

    public function testHydrateFromQueryReturnsEditAction(): void
    {
        $actions = $this->state->hydrateFromQuery(['edit' => '5'], $this->schema);

        $this->assertSame(5, $actions['edit']);
        $this->assertNull($actions['show']);
    }

    public function testHydrateFromQueryReturnsShowAction(): void
    {
        $actions = $this->state->hydrateFromQuery(['show' => '10'], $this->schema);

        $this->assertNull($actions['edit']);
        $this->assertSame(10, $actions['show']);
    }

    public function testHydrateFromQueryRejectsInvalidEditId(): void
    {
        $actions = $this->state->hydrateFromQuery(['edit' => '-1'], $this->schema);

        $this->assertNull($actions['edit']);
    }

    public function testHydrateFromQueryMultipleParams(): void
    {
        $this->state->hydrateFromQuery([
            'page' => '2',
            'sort' => 'name',
            'dir' => 'asc',
            'search' => 'test',
            'fl' => '1',
        ], $this->schema);

        $this->assertSame(2, $this->state->getPage());
        $this->assertSame('name', $this->state->getOrderBy('id'));
        $this->assertSame('ASC', $this->state->getSort('DESC'));
        $this->assertSame('test', $this->state->getFilter('search'));
        $this->assertSame(1, $this->state->getActiveFilterLink());
    }

    // =========================================================================
    // toQueryString tests
    // =========================================================================

    public function testToQueryStringDefaultStateReturnsEmpty(): void
    {
        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('', $qs);
    }

    public function testToQueryStringWithPage(): void
    {
        $this->state->setPage(3);

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('page=3', $qs);
    }

    public function testToQueryStringOmitsPageOne(): void
    {
        $this->state->setPage(1);

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('', $qs);
    }

    public function testToQueryStringWithPerPage(): void
    {
        $this->state->setPerPage(50);

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('pp=50', $qs);
    }

    public function testToQueryStringOmitsDefaultPerPage(): void
    {
        $this->state->setPerPage(15);

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('', $qs);
    }

    public function testToQueryStringWithSort(): void
    {
        $this->state->setOrderBy('email');
        $this->state->setSort('ASC');

        $qs = $this->state->toQueryString($this->schema);

        $this->assertStringContainsString('sort=email', $qs);
        $this->assertStringContainsString('dir=asc', $qs);
    }

    public function testToQueryStringOmitsDefaultSort(): void
    {
        // id DESC is the default
        $this->state->setOrderBy('id');
        $this->state->setSort('DESC');

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('', $qs);
    }

    public function testToQueryStringWithFilterLink(): void
    {
        $this->state->setActiveFilterLink(1);

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('fl=1', $qs);
    }

    public function testToQueryStringWithSearch(): void
    {
        $this->state->setFilter('search', 'john');

        $qs = $this->state->toQueryString($this->schema);

        $this->assertSame('search=john', $qs);
    }

    public function testToQueryStringWithDateRange(): void
    {
        $this->state->setFilter('date_start', '2026-01-01');
        $this->state->setFilter('date_end', '2026-03-01');

        $qs = $this->state->toQueryString($this->schema);

        $this->assertStringContainsString('ds=2026-01-01', $qs);
        $this->assertStringContainsString('de=2026-03-01', $qs);
    }

    public function testToQueryStringWithDropdownFilter(): void
    {
        $this->state->setFilter('dropdowns_0', 'admin');

        $qs = $this->state->toQueryString($this->schema);

        // http_build_query encodes f[0]=admin as f%5B0%5D=admin
        parse_str($qs, $parsed);
        $this->assertSame('admin', $parsed['f']['0']);
    }

    public function testToQueryStringWithOverrides(): void
    {
        $this->state->setPage(5);

        $qs = $this->state->toQueryString($this->schema, ['page' => 3]);

        $this->assertSame('page=3', $qs);
    }

    public function testToQueryStringComplexState(): void
    {
        $this->state->setPage(2);
        $this->state->setPerPage(50);
        $this->state->setOrderBy('name');
        $this->state->setSort('ASC');
        $this->state->setActiveFilterLink(1);
        $this->state->setFilter('search', 'test');

        $qs = $this->state->toQueryString($this->schema);
        parse_str($qs, $parsed);

        $this->assertSame('2', $parsed['page']);
        $this->assertSame('50', $parsed['pp']);
        $this->assertSame('name', $parsed['sort']);
        $this->assertSame('asc', $parsed['dir']);
        $this->assertSame('1', $parsed['fl']);
        $this->assertSame('test', $parsed['search']);
    }

    // =========================================================================
    // Round-trip tests: toQueryString -> hydrateFromQuery
    // =========================================================================

    public function testRoundTrip(): void
    {
        // Set some state
        $this->state->setPage(3);
        $this->state->setPerPage(25);
        $this->state->setOrderBy('email');
        $this->state->setSort('ASC');
        $this->state->setActiveFilterLink(1);
        $this->state->setFilter('search', 'hello');
        $this->state->setFilter('date_start', '2026-01-01');
        $this->state->setFilter('date_end', '2026-03-01');
        $this->state->setFilter('dropdowns_0', 'admin');

        // Encode to query string
        $qs = $this->state->toQueryString($this->schema);

        // Create a fresh state and hydrate from the query string
        $freshState = new ModuleState('test_module_fresh');
        parse_str($qs, $query);
        $freshState->hydrateFromQuery($query, $this->schema);

        // Verify all state was preserved
        $this->assertSame(3, $freshState->getPage());
        $this->assertSame(25, $freshState->getPerPage(15));
        $this->assertSame('email', $freshState->getOrderBy('id'));
        $this->assertSame('ASC', $freshState->getSort('DESC'));
        $this->assertSame(1, $freshState->getActiveFilterLink());
        $this->assertSame('hello', $freshState->getFilter('search'));
        $this->assertSame('2026-01-01', $freshState->getFilter('date_start'));
        $this->assertSame('2026-03-01', $freshState->getFilter('date_end'));
        $this->assertSame('admin', $freshState->getFilter('dropdowns_0'));
    }
}
