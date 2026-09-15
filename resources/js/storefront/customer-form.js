/**
 * Customer data form: which fiscal fields are shown and required for each
 * legal form, input normalisation for tax code, VAT, IPA and CIG, password
 * confirmation, "copy billing address to shipping".
 * Field visibility mirrors App\Support\CustomerFormRules.
 */
export function customerForm(config) {
    const PRIVATE = config.privateType;
    const PUBLIC_ADMIN = config.publicAdminType;

    return {
        type: config.type ?? '',
        copyAddress: false,

        get isPrivate() { return this.type === PRIVATE; },
        get isPublicAdmin() { return this.type === PUBLIC_ADMIN; },
        get hasType() { return this.type !== ''; },
        get requiresVat() { return this.hasType && !this.isPrivate; },

        show(field) {
            switch (field) {
                case 'activity': return this.hasType && !this.isPrivate;
                case 'company': return this.requiresVat;
                case 'tax_code': return this.hasType;
                case 'vat_code': return this.requiresVat;
                case 'pec': return this.requiresVat;
                case 'sdi_code': return this.requiresVat && !this.isPublicAdmin;
                case 'ipa_code': return this.isPublicAdmin;
                case 'cig_code': return this.isPublicAdmin;
                default: return true;
            }
        },

        required(field) {
            switch (field) {
                case 'company': return this.requiresVat;
                case 'tax_code': return this.isPrivate;
                case 'vat_code': return this.requiresVat;
                case 'pec': return this.requiresVat && this.type !== config.companyType;
                case 'ipa_code':
                case 'cig_code': return this.isPublicAdmin;
                default: return false;
            }
        },

        typeChanged() {
            if (this.isPrivate) this.$refs.vat_code && (this.$refs.vat_code.value = '');
            if (!this.isPublicAdmin) {
                if (this.$refs.ipa_code) this.$refs.ipa_code.value = '';
                if (this.$refs.cig_code) this.$refs.cig_code.value = '';
            }
        },

        normalise(input, kind) {
            const rules = {
                tax_code: { clean: (v) => v.toUpperCase().replace(/[^A-Z0-9]/g, ''), valid: (v) => v.length === 16 || v.length === 11, message: config.messages.tax_code },
                vat_code: { clean: (v) => v.replace(/[^0-9]/g, ''), valid: (v) => v.length === 11, message: config.messages.vat_code },
                ipa_code: { clean: (v) => v.toUpperCase().replace(/[^A-Z0-9]/g, ''), valid: (v) => v.length === 6, message: config.messages.ipa_code },
                cig_code: { clean: (v) => v.toUpperCase().replace(/[^A-Z0-9]/g, ''), valid: (v) => v.length === 10, message: config.messages.cig_code },
            };
            const rule = rules[kind];
            if (!rule) return;
            const value = rule.clean(input.value);
            input.value = value;
            const hint = document.getElementById(`${input.id}-hint`);
            if (value !== '' && !rule.valid(value)) {
                input.setCustomValidity(rule.message);
                if (hint) hint.hidden = false;
            } else {
                input.setCustomValidity('');
                if (hint) hint.hidden = true;
            }
        },

        passwordsMatch() {
            const pw = this.$refs.password;
            const pwc = this.$refs.password_confirmation;
            if (!pw || !pwc) return true;
            const mismatch = pw.value && pwc.value && pw.value !== pwc.value;
            pwc.setCustomValidity(mismatch ? config.messages.password_mismatch : '');
            const hint = document.getElementById(`${pwc.id}-hint`);
            if (hint) hint.hidden = !mismatch;
            return !mismatch;
        },

        copyBillingToShipping() {
            ['address', 'city', 'province', 'zip_code', 'country'].forEach((field) => {
                const from = this.$refs[`bill_${field}`];
                const to = this.$refs[`shipping_${field}`];
                if (from && to) to.value = from.value;
            });
        },

        beforeSubmit(event) {
            ['tax_code', 'vat_code', 'ipa_code', 'cig_code'].forEach((field) => {
                const input = this.$refs[field];
                if (!input) return;
                if (this.show(field) && input.value) {
                    this.normalise(input, field);
                } else if (!this.show(field)) {
                    input.setCustomValidity('');
                }
            });
            if (!this.passwordsMatch() || !event.target.checkValidity()) {
                event.preventDefault();
                event.target.reportValidity();
            }
        },
    };
}
