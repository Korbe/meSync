/*
 * Pick a contact from the phone's address book (Contact Picker API).
 * Available in Chrome on Android over HTTPS; iPhone Safari doesn't offer it.
 */

export const canPickContact = typeof window !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window;

/**
 * Opens the phone's contact list and returns customer form fields, or null when cancelled.
 */
export async function pickContact() {
    const supported = await navigator.contacts.getProperties();
    const [contact] = await navigator.contacts.select(supported.filter(p => ['name', 'email', 'tel', 'address'].includes(p)));
    if (!contact) return null;

    // "Maria Anna Huber" → first name "Maria Anna", last name "Huber"
    const parts = (contact.name?.[0] ?? '').trim().split(/\s+/).filter(Boolean);
    const address = contact.address?.[0];

    return {
        first_name: parts.slice(0, -1).join(' '),
        last_name: parts.at(-1) ?? '',
        email: contact.email?.[0] ?? '',
        phone: contact.tel?.[0] ?? '',
        street: address?.addressLine?.join(', ') ?? '',
        zip: address?.postalCode ?? '',
        city: address?.city ?? '',
        country: address?.country ?? '',
    };
}
