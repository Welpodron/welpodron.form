import { form } from '.';

describe('form', () => {
  //! TODO: Добавить тесты мока для fetch
  it('Форма должна быть заблокирована для отправки при инициализации компонента, если присутствуют невалидные поля', () => {
    // INITIAL SETUP
    document.body.innerHTML = `<form>
        <input required />
        <button type="submit">Кнопка отправки с указанием type</button>
        <button>Кнопка отправки без указания type</button>
        <button type="reset">Кнопка сброса</button>
        <button type="button">Обычная кнопка</button>
        <input type="submit" value="Кнопка через input" />
    </form>`;

    const instance = new form({
      element: document.forms[0],
    });

    document.forms[0].querySelectorAll('input').forEach((input) => {
      if (input.type === 'submit') {
        expect(input).toBeDisabled();
      } else if (!input.hasAttribute('disabled')) {
        expect(input).not.toBeDisabled();
      }
    });

    document.forms[0].querySelectorAll('button').forEach((button) => {
      if (button.type === 'submit' || !button.hasAttribute('type')) {
        expect(button).toBeDisabled();
      } else {
        expect(button).not.toBeDisabled();
      }
    });

    //! TODO: Добавить обработку нажатия кнопки и тд
  });
});
