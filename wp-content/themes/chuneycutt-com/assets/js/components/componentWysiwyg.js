import chuneycuttComponent from "./Components";

export class Component extends chuneycuttComponent {
    constructor(name, node) {
        super(name, node);
    }
    init() {
        console.log(this.name);
    }
}