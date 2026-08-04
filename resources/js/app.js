import Sortable from 'sortablejs';

/**
 * Wires every `[data-sortable-section]` container in the Builder canvas
 * (see resources/views/livewire/form-builder/builder.blade.php) to
 * SortableJS, and forwards the new field order to the Livewire component
 * via reorderFields(sectionKey, orderedKeys) on drop.
 *
 * Re-initialised after every Livewire render (`livewire:navigated` /
 * `livewire:update`) since the canvas re-renders when sections/fields
 * change.
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

                // `Livewire` is available globally once @livewireScripts has run.
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
