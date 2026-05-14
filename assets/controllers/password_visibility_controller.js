import { Controller } from '@hotwired/stimulus';

/*
 * Password Visibility Controller
 *
 * Usage with a single icon swapping classes (e.g., Bootstrap Icons):
 * <div data-controller="password-visibility" data-password-visibility-show-class="bi-eye" data-password-visibility-hide-class="bi-eye-slash">
 *   <input type="password" data-password-visibility-target="input">
 *   <button data-action="password-visibility#toggle">
 *     <i class="bi bi-eye" data-password-visibility-target="icon"></i>
 *   </button>
 * </div>
 *
 * Usage with two distinct SVGs/Icons toggling visibility:
 * <div data-controller="password-visibility">
 *   <input type="password" data-password-visibility-target="input">
 *   <button data-action="password-visibility#toggle">
 *     <svg data-password-visibility-target="iconShow">...</svg>
 *     <svg data-password-visibility-target="iconHide" class="d-none">...</svg>
 *   </button>
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'icon', 'iconShow', 'iconHide'];
    static classes = ['show', 'hide'];

    toggle(event) {
        if (event) event.preventDefault();

        const isPassword = this.inputTarget.type === 'password';
        this.inputTarget.type = isPassword ? 'text' : 'password';

        if (this.hasIconTarget && this.hasShowClass && this.hasHideClass) {
            this.iconTarget.classList.toggle(this.showClass, !isPassword);
            this.iconTarget.classList.toggle(this.hideClass, isPassword);
        }

        if (this.hasIconShowTarget) this.iconShowTarget.classList.toggle('d-none', isPassword);
        if (this.hasIconHideTarget) this.iconHideTarget.classList.toggle('d-none', !isPassword);
    }
}