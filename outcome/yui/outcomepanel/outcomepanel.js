YUI.add('moodle-core_outcome-outcomepanel', function(Y) {
    var NAME = 'core_outcome_outcomepanel',

    // Shortcuts, etc
        IO,
        Lang = Y.Lang,
        OUTCOME_SET_LIST = undefined,
        OUTCOME_LIST = undefined,
        EVENT_SAVE = 'save',
        NODE_CONTAINER,
        PANEL = 'panel',
        TEMPLATE_COMPILED,
        CSS = {
            OUTCOME_SET_GROUP: 'yui3-core_outcome_outcomepanel-outcomesetgroup',
            OUTCOME_SET: 'yui3-core_outcome_outcomepanel-outcomeset',
            OUTCOME_LIST: 'yui3-core_outcome_outcomepanel-outcomelist'
        },

        TEMPLATE = '{{#each outcomeSetList}}' +
            '{{#if outcomeList}}' +
            '<div class="{{../../cssOutcomeSetGroup}}">' +
            '<div class="{{../../cssOutcomeSet}}" data-outcomesetid="{{id}}" data-before-aria-label="' + '{{getString "openx" name}}' + '" data-after-aria-label="' + '{{getString "closex" name}}' + '">' +
            '{{name}}' +
            '</div>' +
            '<div class="{{../../cssOutcomeList}}">' +
            '<span class="accesshide" aria-hidden="true">{{getString "outcomesforx" name}}</span>' +
            '{{#each outcomeList}}' +
            '<div class="outcome" tabindex="-1" role="button" data-outcomeid="{{id}}">{{description}}</div>' +
            '{{/each}}' +
            '</div>' +
            '</div>' +
            '{{/if}}' +
            '{{/each}}',

    // Render helpers
        renderGetStringHelper = function(identifier, a) {
            return M.util.get_string(identifier, 'outcome', a);
        };

    var OUTCOMEPANEL = function() {
        OUTCOMEPANEL.superclass.constructor.apply(this, arguments);
    };

    Y.extend(OUTCOMEPANEL, Y.Base,
        {
            /**
             * Do our template setup
             */
            initializer: function() {
                // Register helpers
                Y.Handlebars.registerHelper('getString', renderGetStringHelper);
                TEMPLATE_COMPILED = Y.Handlebars.compile(TEMPLATE);
                IO = new M.core_outcome.SimpleIO({ contextId: this.get('contextId') });
            },

            /**
             * Show the panel
             * @param {Array} selectedOutcomeIds An array of selected outcome IDs
             */
            show_panel: function(selectedOutcomeIds) {
                this._ensure_panel_ready(function() {
                    if (!Lang.isArray(selectedOutcomeIds)) {
                        selectedOutcomeIds = [];
                    }
                    var selectedOutcomes = Y.Array.map(selectedOutcomeIds, function(outcomeId) {
                        return OUTCOME_LIST.getById(outcomeId);
                    });
                    this.get('selectedOutcomes').reset(selectedOutcomes);
                    this.get(PANEL).show();
                });
            },

            /**
             * This goes and fetches our list of outcomes and outcome sets
             * that can be mapped in this context
             * @param callback
             * @private
             */
            _ensure_panel_ready: function(callback) {
                if (OUTCOME_SET_LIST !== undefined && OUTCOME_LIST !== undefined) {
                    callback.call(this);
                    return;
                }
                IO.send({ action: 'get_mappable_outcomes' }, function(data) {
                    if (!Lang.isArray(data.outcomeSetList) || data.outcomeSetList.length === 0 ||
                        !Lang.isArray(data.outcomeList) || data.outcomeList.length === 0) {

                        new M.core.alert({
                            title: M.str.moodle.error,
                            message: M.str.outcome.nooutcomesfound,
                            yesLabel: M.str.outcome.close
                        });
                    } else {
                        OUTCOME_SET_LIST = new M.core_outcome.OutcomeSetList();
                        OUTCOME_LIST = new M.core_outcome.OutcomeList();

                        OUTCOME_SET_LIST.add(data.outcomeSetList);
                        OUTCOME_LIST.add(data.outcomeList);

                        this._render_panel_ui();
                        this._bind_panel_ui();

                        callback.call(this);
                    }
                }, this);
            },

            /**
             * Renders the panel content
             * @private
             */
            _render_panel_ui: function() {
                var list = OUTCOME_SET_LIST.map(function(model) {
                    var data = model.toJSON();
                    data.outcomeList = [];

                    OUTCOME_LIST.each(function(outcome) {
                        if (outcome.get('outcomesetid') == model.get('id')) {
                            data.outcomeList.push(outcome.toJSON());
                        }
                    });
                    return data;
                });

                NODE_CONTAINER = Y.Node.create('<div></div>');
                NODE_CONTAINER.setHTML(TEMPLATE_COMPILED({
                    outcomeSetList: list,
                    cssOutcomeSetGroup: CSS.OUTCOME_SET_GROUP,
                    cssOutcomeSet: CSS.OUTCOME_SET,
                    cssOutcomeList: CSS.OUTCOME_LIST
                }));
                this.get(PANEL).set('bodyContent', NODE_CONTAINER);
            },

            /**
             * Adds listeners to the panel
             * @private
             */
            _bind_panel_ui: function() {
                NODE_CONTAINER.delegate('click', this._handle_select_outcome, '.outcome', this);

                NODE_CONTAINER.all('.' + CSS.OUTCOME_SET_GROUP).each(function(node) {
                    var controlled = node.one('.' + CSS.OUTCOME_LIST);
                    var control = node.one('.' + CSS.OUTCOME_SET);
                    controlled.plug(M.core_outcome.ariacontrolled, {
                        ariaLabelledBy: node.one('span.accesshide'),
                        ariaState: 'aria-expanded'
                    });
                    control.plug(M.core_outcome.ariacontrol, {
                        ariaControls: controlled
                    });
                    control.core_outcome_ariacontrol.on('afterToggle', function() {
                        control.toggleClass('expanded');
                    })
                });

                // Update UI, etc whenever the selected outcomes list changes
                this.get('selectedOutcomes').after(['add', 'remove', 'reset'], this._update_selected_outcome_sets, this);
                this.get('selectedOutcomes').after(['add', 'remove', 'reset'], this._update_selected_outcomes, this);
            },

            /**
             * Handler for when an outcome is selected
             * @param e
             * @private
             */
            _handle_select_outcome: function(e) {
                var outcome = OUTCOME_LIST.getById(e.target.getData('outcomeid'));
                if (Lang.isNull(this.get('selectedOutcomes').getById(outcome.get('id')))) {
                    if (this.get('allowMultiple')) {
                        this.get('selectedOutcomes').add(outcome);
                    } else {
                        this.get('selectedOutcomes').reset([outcome]);
                    }
                } else {
                    this.get('selectedOutcomes').remove(outcome);
                }
            },

            /**
             * Handler for updating the UI after the selected outcomes has changed
             * @private
             */
            _update_selected_outcomes: function() {
                // Update UI
                OUTCOME_LIST.each(function(outcome) {
                    var node = NODE_CONTAINER.one('div[data-outcomeid="' + outcome.get('id') + '"]');

                    if (Lang.isNull(this.get('selectedOutcomes').getById(outcome.get('id')))) {
                        node.removeClass('selected');
                    } else {
                        node.addClass('selected');
                    }
                }, this);
            },

            /**
             * Handler for updating the UI after the selected outcome sets has changed
             * @private
             */
            _update_selected_outcome_sets: function() {
                // First, based on selected outcomes - determine selected outcome sets
                var outcomesetids = Y.Array.dedupe(this.get('selectedOutcomes').get('outcomesetid'));
                var outcomesets = Y.Array.map(outcomesetids, function(outcomesetid) {
                    return OUTCOME_SET_LIST.getById(outcomesetid);
                });
                this.get('selectedOutcomeSets').reset(outcomesets);

                // Update UI
                OUTCOME_SET_LIST.each(function(outcomeset) {
                    var node = NODE_CONTAINER.one('div[data-outcomesetid="' + outcomeset.get('id') + '"]');

                    if (node) {
                        if (Lang.isNull(this.get('selectedOutcomeSets').getById(outcomeset.get('id')))) {
                            node.removeClass('selectedparent');
                        } else {
                            node.addClass('selectedparent');
                        }
                    }
                }, this);
            },

            /**
             * Handler for when the panel is saved
             * Fire off a special event to notify folks that the panel has been saved
             * @param e
             * @private
             */
            _handle_panel_save: function(e) {
                e.preventDefault();
                this.fire(EVENT_SAVE);
                this.get(PANEL).hide();
            },

            /**
             * Handler for when the panel is canceled
             * @param e
             * @private
             */
            _handle_panel_cancel: function(e) {
                e.preventDefault();
                this.get(PANEL).hide();
            },

            /**
             * Creates our panel and attaches listeners
             * @returns {Y.Panel}
             * @private
             */
            _create_panel: function() {
                // todo: what happens if panel gets too tall?
                var panel = new Y.Panel({
                    srcNode: Y.Node.create('<div></div>'),
                    headerContent: M.str.outcome.selectoutcomes,
                    centered: true,
                    render: true,
                    visible: false,
                    modal: true,
                    zIndex: 5000
                });

                panel.plug(M.core_outcome.accessiblepanel);
                panel.addButton({
                    value: M.str.outcome.ok,
                    action: this._handle_panel_save,
                    context: this
                });
                panel.addButton({
                    value: M.str.moodle.cancel,
                    action: this._handle_panel_cancel,
                    context: this
                });

                return panel;
            }
        },
        {
            NAME: NAME,
            ATTRS: {
                /**
                 * Current context ID, used for AJAX requests
                 */
                contextId: {},
                /**
                 * Allow multiple outcomes to be selected or not
                 */
                allowMultiple: { value: true, validator: Lang.isBoolean },
                /**
                 * The list of selected outcomes
                 */
                selectedOutcomes: { value: new M.core_outcome.OutcomeList() },
                /**
                 * The list of selected outcome sets (EG: if an outcome is selected, then its set is added here)
                 */
                selectedOutcomeSets: { value: new M.core_outcome.OutcomeList() },
                /**
                 * The actual panel
                 */
                panel: { readOnly: true, valueFn: '_create_panel' }
            }
        }
    );

    M.core_outcome = M.core_outcome || {};
    M.core_outcome.init_outcomepanel = function(config) {
        return new OUTCOMEPANEL(config);
    };
}, '@VERSION@', {
    requires: ['base', 'panel', 'handlebars', 'moodle-core_outcome-outcomemodel', 'moodle-core_outcome-accessiblepanel', 'moodle-core_outcome-ariacontrol', 'moodle-core_outcome-ariacontrolled', 'moodle-core-notification', 'moodle-core_outcome-simpleio']
});
