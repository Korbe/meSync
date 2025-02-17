import { ref, onMounted } from 'vue';
import axios from 'axios';

export function useEmotions() {
    const primaryEmotions = ref([]); // Liste der primären Emotionen
    const secondaryEmotions = ref([]); // Sekundäre Emotionen für die ausgewählte primäre Emotion
    const emotionsList = ref([]); // Speichert das Array aus der API

    const fetchEmotions = async () => {
        try {
            const response = await axios.get('/emotions/enum');

            // Speichere die vollständige API-Antwort als Array
            emotionsList.value = response.data;

            // Extrahiere die Primäremotionen aus der API-Datenstruktur
            primaryEmotions.value = emotionsList.value.map(emotion => ({
                label: emotion.label,
                value: emotion.value
            }));
        } catch (error) {
            console.error("Fehler beim Laden der Emotionen:", error);
        }
    };

    const updateSecondaryEmotions = (selectedPrimary) => {
        // Finde die passende Primäremotion im Array
        const foundPrimary = emotionsList.value.find(emotion => emotion.value === selectedPrimary);

        // Sekundäre Emotionen setzen (falls gefunden, sonst leere Liste)
        secondaryEmotions.value = foundPrimary ? foundPrimary.options : [];
    };

    onMounted(fetchEmotions);

    return { primaryEmotions, secondaryEmotions, updateSecondaryEmotions };
}
