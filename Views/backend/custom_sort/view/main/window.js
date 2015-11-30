//{namespace name="backend/custom_sort/view/main"}
//{block name="backend/custom_sort/view/main/main"}
Ext.define('Shopware.apps.CustomSort.view.main.Window', {

    extend: 'Enlight.app.Window',

    alias: 'widget.sort-main-window',

    layout: 'border',

    autoShow: true,

    resizable: true,

    maximizable: true,

    minimizable: true,

    width: 1000,

    title: '{s name=window/title}Custom category sorting{/s}',

    /**
     * Sets up the ui component
     * @return void
     */
    initComponent: function () {
        var me = this;

        me.items = me.createItems();

        me.callParent(arguments);
    },

    /**
     * Creates the elements for this component.
     * @return array
     */
    createItems: function () {
        var me = this;

        return [
            {
                xtype: 'sort-category-tree',
                region: 'west',
                flex: 0.25,
                store: me.treeStore
            }, {
                xtype: 'sort-articles-view',
                region: 'center',
                store: me.categorySettings,
                treeStore: me.treeStore,
                articleStore: me.articleStore
            }
        ];
    }

});
//{/block}