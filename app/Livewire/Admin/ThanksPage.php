<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «Поблагодарить автора» — static page listing ways to support the project.
 *
 * Stateless: it holds no properties and exposes no actions; it is a Livewire
 * component purely because every admin screen in this app composes the sidebar
 * layout through #[Layout], and introducing a second layout-composition
 * mechanism for one page would cost more than this class does.
 *
 * Routed at /admin/thanks — deliberately OUTSIDE the /admin/settings prefix and
 * its EnsureSettingsAccess guard, because thanking the author is not a setting
 * and must stay reachable by every signed-in operator, not admins only.
 *
 * Layout: layouts.admin-settings (dark sidebar 280px + content area).
 */
#[Layout('layouts.admin-settings')]
class ThanksPage extends Component
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('livewire.admin.thanks-page');
    }
}
