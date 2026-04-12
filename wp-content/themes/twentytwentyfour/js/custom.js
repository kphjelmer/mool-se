console.log('✅ custom.js är laddad');

(function () {
    function initAmeliaButton() {
        const amBtn = document.querySelector(
            '#amelia-container .am-fcis__header-action .am-button, ' +
            '#amelia-container .am-fc__btn'
        );

        if (!amBtn) {
            console.log('⏳ Hittar ingen Amelia-knapp ännu…');
            return false;
        }

        console.log('🎯 Amelia-knapp hittad:', amBtn);

        /* === 1. Sätt texten till "Boka tid" === */
        amBtn.textContent = 'Boka tid';

        /* === 2. Lägg till Elementor-klasser === */
        amBtn.classList.add(
            'elementor-button',
            'elementor-button-link',
            'elementor-size-sm',
            'elementor-animation-grow'
        );

        /* === 3. Ta bort Amelia inline-styles som förstör designen === */
        amBtn.removeAttribute('style');

        /* === 4. Vänsterställ knappen === */
        const wrapper = amBtn.closest('.am-fcis__header-action');
        if (wrapper) {
            wrapper.style.display = 'flex';
            wrapper.style.justifyContent = 'flex-start'; // vänsterställd
        }

        console.log('✅ Amelia-knappen är nu stylad som Mool-knappen');
        return true;
    }

    // Försök direkt…
    if (!initAmeliaButton()) {
        // …och fortsätt leta var 500 ms tills knappen finns
        const intervalId = setInterval(function () {
            if (initAmeliaButton()) {
                clearInterval(intervalId);
            }
        }, 500);
    }
})();