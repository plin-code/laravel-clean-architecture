import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';

export default function Profile() {
    return <Form {...ProfileController.update.form()} action={edit()} />;
}
