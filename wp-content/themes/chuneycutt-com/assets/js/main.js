// Check for component chunks
document.addEventListener("DOMContentLoaded", () => {

    const checkForElement = (element) => {
        if (typeof element != "undefined") {
            return element;
        }
        return false;
    };

    const components = document.querySelectorAll(".component");
    if (!components.length) return;

    let componentsArray = [];
    components.forEach(async (module) => {
        let classList = checkForElement(module.classList);
        let name = classList[1];
        if (name) {
            name = camelCase(name);
            if ($.inArray(name, componentsArray) == -1) {
                componentsArray.push(name);
            }
        } else {
            console.error("No component name found");
            return;
        }
    });

    if (componentsArray.length) {
        componentsArray.forEach(async (name) => {
            try {
                const { Component } = await import(/* webpackChunkName: 'component' */ `./components/${name}`);
                const component = new Component(name);
                component.init();
            } catch (error) {
                console.log(error);
                console.warn(
                    `You're missing the js file for the ${name} component. \n Create the missing file inside the components folder: /themes/instaclustr/assets/js/components/${name}.js`
                );
            }
        });
    }
});
