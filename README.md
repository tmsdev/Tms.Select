# NodeData DataSource for SelectBoxEditors

Select nodes instead of searching via reference editor in Neos CMS.

## Install

```bash
composer require tms/select
```

## Usage

```yaml
'Your.Package:Type':
  properties:
    yourReference:
      type: reference
      ui:
        inspector:
          editor: 'Neos.Neos/Inspector/Editors/SelectBoxEditor'
          editorOptions:
            dataSourceIdentifier: 'tms-select-nodedata'
            dataSourceAdditionalData:
              nodeTypes: ['Your.Package:TypeThatShouldBeReferenced']
              # Optional parameters
              groupBy: 'Your.Package:GroupType'
              startingPoint: '/start/here/instead/of/rootnode'
              labelPropertyName: 'title'
              previewPropertyName: 'thumbnailImage' # works with Neos 7.2+
```

### Optional parameters
| Parameter name        | Description |
|-----------------------|---|
| `labelPropertyName`   | Choose your specific **text property name** - if not set, the nodes label will be used. |
| `previewPropertyName` | Choose your specific **image property name** to display a custom preview icon as mentioned in the [Neos 7.2 release notes](https://www.neos.io/blog/neos-flow-72-released.html#neos-7-1-features). |

## Acknowledgments
Development sponsored by [tms.development - Online Marketing and Neos CMS Agency](https://www.tms-development.de/)
