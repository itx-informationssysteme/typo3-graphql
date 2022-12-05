# GraphQL TYPO3 Extension

This extension provides a GraphQL API for TYPO3. It does that by exposing the TYPO3 data model as a GraphQL schema.
The schema is fully configurable. The GraphQL API can be accessed via the `/graphql` endpoint. The extension uses the TCA to automatically
generate the schema.

> :warning: This extension is still in development. Expect breaking changes and things to be broken or not implemented yet.

## ✨ Features
* Automatic resolving of relations
* Link generation
* URL generation for images and files

## Installation

`composer require itx/typo3-graphql`

## 🔧 Configuration

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

## 💻 Usage

The extension provides a `Query` type, which is the entry point for all queries. The `Query` type has a field for each model you
configured to be queryable.
Each configured model has a field to query multiple instances of the model, and a field to query a single instance of the model.

### 👀 Introspection

GraphQL Introspection is enabled by default in development mode and disabled in production.

### ⚡ Filter API

The extension also comes with an extensive filter and facet system. Right now, it supports discrete filters only.

You can add filter records anywhere in the page tree. Each filter defines a name, the model it applies to, and the filter path to the
field it applies to.
The filter path is a dot separated list of field names. This means it is possible to use the Extbase Repository field path syntax
to filter on model relations.

When you have configured the filter in the backend, you can use it in your GraphQL query.

Example query to query filter options without filtering:

```
postings(filters: { discreteFilters: [{ path: "locations.name", options: [] }] }) {
    facets {
        label
        options {
            value
            resultCount
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
        label
        options {
            value
            resultCount
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

In the query above the filter options will be filtered as well in order to be able to disable options that are not available to
prevent impossible filter option combinations.

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

Both work the same way. You can add a field to the schema by adding one or more `FieldBuilder` instances to the event.
These provide the schema, as well as the resolver functions. See
the [php-graphql docs](https://webonyx.github.io/graphql-php/schema-definition) for more information. For convenience, the
extension uses the `simpod/graphql-utils` package, which provides a `FieldBuilder` class instead of configuration arrays.

The `build` method of the root field builders is called by the extension.

