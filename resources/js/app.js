import './bootstrap';
import Sortable from 'sortablejs';


// Pages with a Livewire component (builder, import, generate, submissions)
// get Alpine bundled automatically via @livewireScripts. The dashboard has
// no Livewire component at all, so it never receives Alpine that way —
// Breeze's nav dropdown needs it directly. Waiting for DOMContentLoaded
// guarantees both possible script sources have already run, avoiding a
// race between Livewire's script tag and this module.
window.addEventListener('DOMContentLoaded', async () => {
    if (! window.Alpine) {
        const { default: Alpine } = await import('alpinejs');
        window.Alpine = Alpine;
        Alpine.start();
    }
});


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