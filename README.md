# GraphQL TYPO3 Extension

This extension provides a [GraphQL](https://graphql.org/) API for TYPO3. It does that by exposing the TYPO3 data models as a GraphQL schema.

Any table can be configured to be available in the API. The schema is fully configurable.
The GraphQL API can be accessed via the `/graphql` endpoint.

> :warning: This extension is not fully feature complete yet. Expect things to be broken or not implemented yet.

## ✨ Features
* Automatic resolving of relations
* Link generation
* URL generation for images and files
* Filter and facets API
* Pagination
* Basic Languages support

## What does not work yet?
* Not every TCA field is supported yet.
* Language overlays don't work yet.
* There are probably other problems: Please create an issue if you find one or create a pull request.

## 🔨 Installation

`composer require itx/typo3-graphql`

## ⚙️ Configuration

The extension uses two mechanisms to configure the GraphQL schema.

1. To configure the schema, put a `GraphQL.yaml` file into the `Configuration` directory of your extension or site package.
    * This file will be automatically loaded and merged with the configuration file of other extensions.
    * See the [example configuration](Configuration/GraphQL.example.yaml) for more details. It's works by default, which makes it
      a good starting point.
2. Inside the GraphQL.yaml file, you can define which Domain Model you want to expose, and if you want it to be queryable or not.
    * For each model, you can either use an existing one, or define a new one. You can see this as a "view" on the model.
    * The reason for this is, that you may not want to expose all fields of a model, or you may want to expose a model in a
      different way.
    * For each field in your custom model, you can define via the `@Expose` annotation, if you want to expose the field.
    * If you build custom models, for the sole purpose of exposing them via GraphQL, you can use the `@ExposeAll` annotation to
      expose all fields in the model. Use this with caution.
    * Make sure your model is also correctly registered in the Extbase Persistence Configuration. See
      the [extensions own persistence configuration](Configuration/Extbase/Persistence/Classes.php) for an example on how to do
      that.
    * Also make sure when overriding ObjectStorages, to include the correct ObjectStorage var Annotation with the correct Type.

> :warning: Every field you mark with `@Expose` or `@ExposeAll` will be publicly accessible in the GraphQL API.

## 💻 Usage

The extension provides a `Query` type, which is the entry point for all queries. The `Query` type has a field for each model you
configured to be queryable.
Each configured model has a field to query multiple instances of the model, and a field to query a single instance of the model.

### 👀 Introspection

GraphQL Introspection is enabled by default in development mode and disabled in production.

### ⚡ Filter API

The extension also comes with an extensive filter and facet system. Right now, it supports discrete filters and range filters.

There are two ways to configure filters:
* Via the backend in the TYPO3 backend
* Via the yaml configuration file
The backend configuration could be useful if you need to assign some logic to
the filters and need data to be editable in the backend.
The yaml configuration file is static and can be used to configure filters that
you know won't change.
Both ways will be merged together at runtime.

#### Via the backend
You can add filter records anywhere in the page tree. Each filter defines a name, the model it applies to, and the filter path to the
field it applies to.
The filter path is a dot separated list of field names. This means it is possible to use the Extbase Repository field path syntax
to filter on model relations.

#### Via the yaml configuration file
You can also configure filters via the yaml configuration file. See the [example configuration](Configuration/GraphQL.example.yaml) for more details.
The fields are the same as in the backend.

When you have configured the filters, you can use it in your GraphQL query like this:

Example query to query filter options without filtering:

```
postings(filters: { discreteFilters: [{ path: "locations.name", options: [] }] }) {
    facets {
        label
        options {
            value
            resultCount
            disabled
        }
        path
    }
    edges {
        node {
            title
        }
    }
}
```

The above query will return the filter options for the `locations.name` field of the Posting model.

If you want to apply one or more filter options you can use the `options` argument of the `discreteFilters` field.

Example querying with filtering:

```
postings(filters: { discreteFilters: [{ path: "locations.name", options: ["Testlocation"] }] }) {
    facets {
        ... on DiscreteFacet {
            label
            options {
                value
                resultCount
            }
            path
        }
    }
    edges {
        node {
            title
        }
    }
}
```

This will return the postings that have a location with the name `Testlocation`.
You will also be able to notice that the filter options of other filters are filtered as well in order to be able to disable options that are not available to
prevent impossible filter option combinations (e.g combinations that would return no results).

Currently there are two types of filters available:
* `DiscreteFilter` - This filter type allows you to select one or more options from a list of options.
* `RangeFilter` - This filter type allows you to select a range of values.

In the query above the filter options will be filtered as well in order to be able to disable options that are not available to
prevent impossible filter option combinations. Whether a filter option will still get results or not is shown by the `disabled` field.

> Make sure to always have a filter record for the type of filter you want to use. Otherwise, the filter will not be available in
> the GraphQL API.

## 🪩 Events

### Extend the schema with custom virtual fields

The extension provides a few events to allow extensions to extend the schema. They are standard PSR-14 events, so you
can [easily use them inside your own extension](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/EventDispatcher/Index.html#implementing-an-event-listener-in-your-extension)
.
The events are:

1. `Itx\Typo3GraphQL\Events\CustomModelFieldEvent` - Allows you to add custom fields to a model.
2. `Itx\Typo3GraphQL\Events\CustomQueryFieldEvent` - Allows you to add custom fields to the `Query` type.
3. `Itx\Typo3GraphQL\Events\CustomQueryArgumentEvent` - Allows you to add custom arguments to a query field.
4. `Itx\Typo3GraphQL\Events\ModifyQueryBuilderForFilteringEvent` - Allows you to modify the query builder used for filtering.

Both work the same way. You can add a field to the schema by adding one or more `FieldBuilder` instances to the event.
These provide the schema, as well as the resolver functions. See
the [php-graphql docs](https://webonyx.github.io/graphql-php/schema-definition) for more information. For convenience, the
extension uses the `simpod/graphql-utils` package, which provides a `FieldBuilder` class instead of configuration arrays.

The `build` method of the root field builders is called by the extension.

