# ReachDigital_UrlFixes

Magento 1 module for preventing and cleaning up the URL rewrite mess that can result from duplicate product URL keys.
Typical symptons of this are:

- Number suffixes (-1234) appearing in product URLs
- Product URLs number suffixes keep changing
- Products ending up with entirely incorrect URLs, containing a different products' URL key
- Rewrite table keeps growing in size, and increasing indexing time

This module has been written as an alternative to some other modules which add some extra behavior that does not always
work as expected, or do not fully account for some more elaborate multistore shops. This module comes with a fallback
mechanism, allowing complete sanitation of product URLs while ensure old indexed URLs keep working.

Features:

- Tested on a international multi-store webshop, with 10+ stores and a complex category structure
- Prevents new URL conflicts, warns about existing conflicts
- Prevents URL key conflicts due to product duplication
- Allows completely sanitizing URL keys without risking a negative SEO impact  
- Tools diagnose and analyze URL key conflicts 
- Helper methods to assist with automated sanitation of product URL keys
- Dependency on MageHost_RewriteFix to fix core bug responsible for redundant rewrites being created at every reindex 

This module does not change the behavior of the catalog URL indexer (other than the bug fix applied by
MageHost_RewriteFix).

## Changes that help prevent new URL key conflicts 

- Fixes incorrect URLs after duplicating products by fully clearing url_key and name (default value and any store
values) attribute values for the new product new product
- Depends on MageHost_RewriteFix to fix the Magento core bug that would cause, if there are or have ever been URL key
conflicts, many pointless rewrites being added on every reindex
- When saving a product in the backend, a warning is emitted if it has any URL key conflicts with other products. The
warning will show the conflicting products and in which store they conflict
- When saving a product with a _new_ URL key that would result in a conflict, the URL key is reverted and a warning is
shown

## Fallback mechanism

This module implements a fallback mechanism so that after cleaning URL keys and regenerating the rewrite table from
scratch, old URLs continue to work and will redirect to the current, clean URL.

This works by keeping a full copy of the rewrite table (as it was before it was regenerated), and a 404
catcher that checks if a request (that would result in a 404), matches a URL in the fallback table. If it does, a
permanent redirect is performed to the correct URL by matching product/category IDs. The fallback lookup does not
redirect if the incoming URL is also in the current rewrite table; this means the URL is valid but the product or
category was not visible.

The fallback table stores the number of hits, and datetime of most recent hit. Over time, as crawlers have seen
the redirect, the fallback table can safely be truncated. 

## Installation guide

Warning: this module may contain bugs! It has not been tested incombination with other modules that change the catalog
URL index behavior other than MageHost_RewriteFix. Thoroughly test all below steps in non-production environment, and
have full backups at every step of the way. 

### Step 1 - Installation

Install this module and its dependency MageHost_RewriteFix. This is the only thing you need to do if you're only
interested in the bug fixes or features that prevent future URL key conflicts. The following steps can be followed to
clean up existing URL key conflicts and start with a clean rewrite table.

### Step 2 - Fill fallback table with existing rewrites

If you want to retain old URLs and have them properly redirect, a copy of the current rewrite table (core_url_rewrite)
must be made. This module creates a separate table for this automatically, which can be filled with the following shell
command:

```
php reachdigital_urlfixes.php -action fillFallbackTable
```

### Step 3 - Analyzing and fixing URL key conflicts

A complete per-store overview of all URL key conflicts can be generated with:

```
php reachdigital_urlfixes.php -action dumpProductConflicts > conflicts.lst
```

Use `\ReachDigital_UrlFixes_Helper_Url::getAllProductUrlKeyConflicts` to get the raw conflict data.

Programmatically resolving all conflicts (for example in a custom shell script) in your development environment
allows iterative improvements and validation before applying them in production. 

The `ReachDigital_UrlFixes_Helper_Product` class has convenience methods for setting, clearing and
regenerating URL keys.

Note that category URL key conflicts should be resolved as well. This module provides no help for that, but usually
there aren't many of these and can be easily resolved (tip: check the category flat tables if you have those enabled).

### Step 4 - Regenerating rewrite table 

After all conflicts have been resolved, a new clean rewrite table can be generated using:

```
php reachdigital_urlfixes.php -action regenerateRewriteTable

```

### Step 5 - Clean fallback table

Eventually, the fallback table can be cleaned once crawlers pick up the redirects. Once you are sure the old URLs are no
longer being accessed, you can truncate the fallback table. Whenever a fallback URL is hit, the 'hits' column is
incremented, and the 'last_requested_at' columns are updated. Use these to determine if it is safe to truncate the
table.


## Useful queries

Finding URL key values ending with numbers (might've been written back by Loewenstark module):

```
select * from catalog_product_entity_varchar
where attribute_id = 97 and value regexp '-[0-9]+$'
```

Finding generated rewrites ending with numbers:

```
select * from core_url_rewrite
where request_path regexp '-[0-9]+\.html$'
order by request_path
```

## TODO

- Also check conflicts on category save
- Also help with cleaning categories (usually not too hard to fix by hand)
- Also check for conflicts in rewrite table (instead of only URL key attribute values) on product save
- Improve automation, add examples
