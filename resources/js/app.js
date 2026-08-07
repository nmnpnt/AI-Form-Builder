import './bootstrap';
import Sortable from 'sortablejs';

/**
 * Wires every `[data-sortable-section]` container in the Builder canvas to
 * SortableJS, and forwards the new field order to the Livewire component
 * via reorderFields(sectionKey, orderedKeys) on drop.
 */
function initSortableSections() {
    document.querySelectorAll('[data-sortable-section]').forEach((el) => {
        if (el.dataset.sortableInitialised) {
            return;
        }
        el.dataset.sortableInitialised = 'true';

        const sectionKey = el.dataset.sortableSection;

        Sortable.create(el, {
            animation: 150,
            handle: '[data-drag-handle]',
            onEnd: () => {
                const orderedKeys = Array.from(el.children)
                    .map((child) => child.dataset.fieldKey)
                    .filter(Boolean);

                const component = el.closest('[wire\\:id]');
                if (component) {
                    window.Livewire.find(component.getAttribute('wire:id'))
                        .call('reorderFields', sectionKey, orderedKeys);
                }
            },
        });
    });
}

document.addEventListener('livewire:navigated', initSortableSections);
document.addEventListener('livewire:init', initSortableSections);
document.addEventListener('livewire:update', initSortableSections);
window.addEventListener('DOMContentLoaded', initSortableSections);