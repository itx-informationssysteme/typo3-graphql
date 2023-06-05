<?php

namespace Itx\Typo3GraphQL\Utility;

class QueryArgumentsUtility
{
    public static string $language = 'language';
    public static string $pageIds = 'pageIds';

    public static string $uid = 'uid';

    public static string $paginationFirst = 'first';
    public static string $paginationAfter = 'after';

    public static string $offset = 'offset';

    public static string $sortByField = 'sortBy';
    public static string $sortingOrder = 'sorting';

    public static string $filters = 'filters';
    public static string $discreteFilters = 'discreteFilters';
    public static string $filterPath = 'filterPath';
}
