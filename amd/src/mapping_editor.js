/** Guided JSON mapping editor for the Moodle admin setting. */
define([], function() {
    'use strict';

    const create = (tag, className, content) => {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (content !== undefined) {
            node.textContent = content;
        }
        return node;
    };

    const init = id => {
        const configNode = document.getElementById(id + '-editor-config');
        if (!configNode) {
            return;
        }
        const config = JSON.parse(configNode.textContent);
        const textarea = document.getElementById(config.id);
        if (!textarea) {
            return;
        }
        const s = config.strings;
        const help = config.help || {};
        const modules = config.modules;
        const owned = new Set(config.owned);
        let rules;
        try {
            const parsed = JSON.parse(textarea.value);
            rules = Array.isArray(parsed) ? parsed : parsed.rules;
            if (!Array.isArray(rules)) {
                rules = [];
            }
        } catch (e) {
            rules = [];
        }

        const panel = create('div', 'local-reschedule-mapping-editor border rounded p-3 mb-3');
        panel.appendChild(create('h3', 'h5', s.heading));
        const list = create('div', 'local-reschedule-mapping-list');
        panel.appendChild(list);
        const add = create('button', 'btn btn-primary my-2', s.add);
        add.type = 'button';
        panel.appendChild(add);
        const appendHelp = (parent, key) => {
            if (!help[key]) {
                return;
            }
            const holder = create('span', 'ms-1');
            // Moodle rendered and escaped this help_icon on the server.
            holder.innerHTML = help[key];
            parent.appendChild(holder);
        };
        const raw = create('details', 'mt-3');
        const rawsummary = create('summary', '', s.raw);
        raw.appendChild(rawsummary);
        const rawhelp = create('div', 'small mt-2');
        rawhelp.appendChild(create('span', '', s.raw));
        appendHelp(rawhelp, 'raw');
        raw.appendChild(rawhelp);
        textarea.parentNode.insertBefore(panel, textarea);
        textarea.parentNode.insertBefore(raw, textarea);
        raw.appendChild(textarea);
        textarea.classList.add('w-100', 'mt-2');

        const write = () => {
            textarea.value = JSON.stringify(rules, null, 2);
        };
        const expandedRules = new Set();
        let suggestionid = 0;
        let controlid = 0;
        const getCompatibilityIssues = (rule, index) => {
            const issues = [];
            const moduleName = rule.module || '';
            const info = modules[moduleName];
            if (owned.has(moduleName) || owned.has(rule.parent)) {
                issues.push(s.ownedrule);
            }
            if (!info) {
                issues.push(s.incompatiblemodule + ': ' + (moduleName || '—'));
                return issues;
            }
            if (!rule.parent && !info.hascourse) {
                issues.push(s.incompatiblecourse + ': ' + moduleName + '.course');
            }
            if (rule.parent && !rules.some(candidate => !candidate.parent && candidate.module === rule.parent)) {
                issues.push(s.incompatibleparent + ': ' + rule.parent);
            }
            if (!rule.parent && rules.some((candidate, candidateIndex) => candidateIndex !== index &&
                    !candidate.parent && candidate.module === moduleName)) {
                issues.push(s.incompatibleduplicate + ': ' + moduleName);
            }
            const fields = [rule.title, rule.start];
            if (rule.kind === 'range') {
                fields.push(rule.end);
            }
            if (rule.parent && rule.parent !== moduleName) {
                fields.push(rule.fk || rule.parent + 'id');
            }
            const columns = new Set(info.columns || []);
            fields.filter(Boolean).filter(field => !columns.has(field)).forEach(field => {
                issues.push(s.incompatiblefield + ': ' + moduleName + '.' + field);
            });
            return issues;
        };
        const markCompatibility = (card, rule, index) => {
            const issues = getCompatibilityIssues(rule, index);
            const summary = card.querySelector('.local-reschedule-mapping-summary');
            const body = card.querySelector('.local-reschedule-mapping-rule-body');
            const previous = body.querySelector('.local-reschedule-mapping-incompatibility');
            if (previous) {
                previous.remove();
            }
            card.classList.toggle('is-incompatible', issues.length > 0);
            summary.setAttribute('aria-invalid', issues.length > 0 ? 'true' : 'false');
            summary.title = issues.join('\n');
            if (!issues.length) {
                return;
            }
            const alert = create('div',
                'local-reschedule-mapping-incompatibility alert alert-danger py-2 mb-3');
            alert.appendChild(create('strong', 'd-block', s.incompatible));
            const details = create('ul', 'mb-0 ps-3');
            issues.forEach(issue => details.appendChild(create('li', '', issue)));
            alert.appendChild(details);
            body.prepend(alert);
        };
        const refreshCompatibility = () => {
            list.querySelectorAll('.local-reschedule-mapping-rule').forEach((card, index) => {
                markCompatibility(card, rules[index], index);
            });
        };
        const addHeading = (container, input, label, key) => {
            input.id = 'reschedule-mapping-field-' + (++controlid);
            const heading = create('div', 'd-flex align-items-center');
            const title = create('label', 'fw-semibold mb-0', label);
            title.htmlFor = input.id;
            heading.appendChild(title);
            appendHelp(heading, key);
            container.appendChild(heading);
        };
        const addField = (container, rule, key, label, choices, type) => {
            const wrap = create('div', 'd-flex flex-column small mb-2');
            let input;
            if (type === 'check') {
                input = create('input', 'form-check-input mt-1');
                input.type = 'checkbox';
                input.checked = ['editable', 'boundstart', 'boundend'].includes(key) ?
                    rule[key] !== false : Boolean(rule[key]);
            } else if (choices && choices.length && type !== 'suggest') {
                input = create('select', 'form-select form-select-sm');
                choices.forEach(choice => {
                    const option = create('option', '', choice[1]);
                    option.value = choice[0];
                    input.appendChild(option);
                });
                input.value = rule[key] || '';
            } else {
                input = create('input', 'form-control form-control-sm');
                input.type = type === 'number' ? 'number' : 'text';
                if (type === 'number') {
                    input.min = '0';
                    input.step = '0.25';
                }
                input.value = rule[key] ?? '';
                if (type === 'suggest' && choices && choices.length) {
                    const datalist = create('datalist');
                    datalist.id = 'reschedule-field-' + (++suggestionid);
                    choices.forEach(choice => {
                        const option = create('option');
                        option.value = choice[0];
                        option.label = choice[1];
                        datalist.appendChild(option);
                    });
                    input.setAttribute('list', datalist.id);
                    wrap.appendChild(datalist);
                }
            }
            input.addEventListener(type === 'check' || choices ? 'change' : 'input', () => {
                if (type === 'number' && input.value === '') {
                    delete rule[key];
                } else {
                    var value = input.value;
                    if (type === 'check') {
                        value = input.checked;
                    } else if (type === 'number') {
                        value = Number(input.value);
                    }
                    rule[key] = value;
                }
                if (key === 'kind') {
                    if (input.value !== 'range') {
                        rule.end = '';
                        if (rule.effort && rule.effort.model === 'perday') {
                            delete rule.effort;
                        }
                    }
                    render();
                } else if (key === 'module') {
                    const info = modules[input.value];
                    if (info) {
                        rule.title = info.title || rule.title;
                        const formpair = info.pairs.find(pair => {
                            const start = info.fields.find(field => field.name === pair.start);
                            const end = info.fields.find(field => field.name === pair.end);
                            return start && end && start.origin === 'mod_form' && end.origin === 'mod_form';
                        });
                        const formfield = info.fields.find(field => field.origin === 'mod_form');
                        if (formpair) {
                            rule.start = formpair.start;
                            rule.end = formpair.end;
                            rule.kind = 'range';
                        } else if (formfield || info.fields.length) {
                            const field = formfield || info.fields[0];
                            rule.start = field.name;
                            rule.end = '';
                            rule.kind = 'milestone';
                            rule.dateonly = field.dateonly;
                        }
                        render();
                    }
                }
                write();
                refreshCompatibility();
            });
            addHeading(wrap, input, label, key);
            wrap.appendChild(input);
            container.appendChild(wrap);
            return input;
        };
        const render = () => {
            list.replaceChildren();
            rules.forEach((rule, index) => {
                const card = create('details', 'local-reschedule-mapping-rule border rounded my-2');
                card.open = expandedRules.has(rule);
                card.addEventListener('toggle', () => {
                    if (card.open) {
                        expandedRules.add(rule);
                    } else {
                        expandedRules.delete(rule);
                    }
                });
                const summary = create('summary',
                    'local-reschedule-mapping-summary d-flex align-items-center gap-2 p-3');
                const title = create('strong', 'me-auto',
                    (index + 1) + '. ' + (rule.label || rule.module || s.add));
                summary.appendChild(title);
                const kindLabels = {range: s.range, milestone: s.milestone, open: s.open};
                summary.appendChild(create('span', 'badge bg-light text-secondary border',
                    s.type + ': ' + (kindLabels[rule.kind] || rule.kind || s.range)));
                const remove = create('button', 'btn btn-sm btn-outline-danger', s.remove);
                remove.type = 'button';
                remove.addEventListener('click', event => {
                    event.preventDefault();
                    event.stopPropagation();
                    expandedRules.delete(rule);
                    rules.splice(index, 1);
                    write();
                    render();
                });
                summary.appendChild(remove);
                card.appendChild(summary);
                const body = create('div', 'local-reschedule-mapping-rule-body px-3 pb-3');
                const grid = create('div', 'row row-cols-1 row-cols-md-3 g-2');
                const cell = () => {
                    const col = create('div', 'col');
                    grid.appendChild(col);
                    return col;
                };
                const moduleinput = addField(cell(), rule, 'module', s.module);
                const datalist = create('datalist');
                datalist.id = 'reschedule-modules-' + index;
                Object.keys(modules).filter(name => !owned.has(name)).forEach(name => {
                    const option = create('option');
                    option.value = name;
                    datalist.appendChild(option);
                });
                moduleinput.setAttribute('list', datalist.id);
                body.appendChild(datalist);
                addField(cell(), rule, 'kind', s.type, [
                    ['range', s.range], ['milestone', s.milestone], ['open', s.open],
                ]);
                addField(cell(), rule, 'label', s.label);
                addField(cell(), rule, 'title', s.title);
                const info = modules[rule.module];
                const datechoices = info ? info.fields.map(field => [field.name,
                    field.name + ' (' + (field.origin === 'mod_form' ? s.originform : s.originschema) + ')']) : [];
                addField(cell(), rule, 'start', s.start, datechoices, 'suggest');
                if (rule.kind === 'range') {
                    addField(cell(), rule, 'end', s.end, datechoices, 'suggest');
                }
                addField(cell(), rule, 'parent', s.parent);
                addField(cell(), rule, 'fk', s.fk);
                addField(cell(), rule, 'availability', s.availability, [
                    ['auto', 'auto'], ['off', 'off'],
                ]);
                if (typeof rule.effort === 'number') {
                    rule.effort = {model: 'fixed', hours: rule.effort};
                }
                const effort = rule.effort;
                const effortwrap = create('div', 'col');
                const modellabel = create('div', 'd-flex flex-column small mb-2');
                const modelselect = create('select', 'form-select form-select-sm');
                [['', s.effortdefault], ['fixed', s.effortfixed], ['perday', s.effortperday]].forEach(choice => {
                    const option = create('option', '', choice[1]);
                    option.value = choice[0];
                    option.disabled = choice[0] === 'perday' && rule.kind !== 'range';
                    modelselect.appendChild(option);
                });
                modelselect.value = effort && effort.model || '';
                modelselect.addEventListener('change', () => {
                    if (!modelselect.value) {
                        delete rule.effort;
                    } else if (modelselect.value === 'fixed') {
                        rule.effort = {model: 'fixed', hours: 1};
                    } else {
                        rule.effort = {model: 'perday', hoursperday: 1};
                    }
                    write();
                    render();
                });
                addHeading(modellabel, modelselect, s.effortmodel, 'effortmodel');
                modellabel.appendChild(modelselect);
                effortwrap.appendChild(modellabel);
                grid.appendChild(effortwrap);
                if (effort && effort.model) {
                    const numberwrap = create('div', 'col');
                    const numberlabel = create('div', 'd-flex flex-column small mb-2');
                    const numberkey = effort.model === 'fixed' ? 'efforthours' : 'efforthoursperday';
                    const numberinput = create('input', 'form-control form-control-sm');
                    numberinput.type = 'number';
                    numberinput.min = '0';
                    numberinput.step = '0.25';
                    numberinput.value = effort.model === 'fixed' ? effort.hours : effort.hoursperday;
                    numberinput.addEventListener('input', () => {
                        rule.effort[effort.model === 'fixed' ? 'hours' : 'hoursperday'] = Number(numberinput.value);
                        write();
                    });
                    addHeading(numberlabel, numberinput, s[numberkey], numberkey);
                    numberlabel.appendChild(numberinput);
                    numberwrap.appendChild(numberlabel);
                    grid.appendChild(numberwrap);
                }
                ['optional', 'dateonly', 'boundstart', 'boundend', 'editable'].forEach(key => {
                    addField(cell(), rule, key, s[key], null, 'check');
                });
                body.appendChild(grid);
                if (info && info.pairs.length) {
                    const suggestions = create('div', 'mt-2 small');
                    suggestions.appendChild(create('span', 'me-2', s.suggestions + ':'));
                    info.pairs.forEach(pair => {
                        const button = create('button', 'btn btn-sm btn-outline-secondary me-1 mb-1',
                            pair.start + ' → ' + pair.end);
                        button.type = 'button';
                        button.addEventListener('click', () => {
                            rule.kind = 'range';
                            rule.start = pair.start;
                            rule.end = pair.end;
                            write();
                            render();
                        });
                        suggestions.appendChild(button);
                    });
                    body.appendChild(suggestions);
                }
                card.appendChild(body);
                list.appendChild(card);
                markCompatibility(card, rule, index);
            });
        };
        add.addEventListener('click', () => {
            const rule = {module: '', title: 'name', label: '', kind: 'range', start: '', end: '',
                availability: 'auto', editable: true};
            rules.push(rule);
            expandedRules.add(rule);
            write();
            render();
        });
        textarea.addEventListener('change', () => {
            if (!raw.open) {
                return;
            }
            try {
                const parsed = JSON.parse(textarea.value);
                const entries = Array.isArray(parsed) ? parsed : parsed.rules;
                if (Array.isArray(entries)) {
                    rules = entries;
                    render();
                }
            } catch (e) {
                // Moodle's server validation will report malformed JSON on save.
            }
        });
        render();
    };

    return {init};
});
