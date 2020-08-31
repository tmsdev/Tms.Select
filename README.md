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
```

## Acknowledgments

Development sponsored by [tms.development - Online Marketing and Neos CMS Agency](https://www.tms-development.de/)
