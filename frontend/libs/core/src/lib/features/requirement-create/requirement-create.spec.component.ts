import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach } from 'vitest';
import { RequirementCreateComponent } from './requirement-create.component';


describe('RequirementCreate', () => {
  let component: RequirementCreateComponent;
  let fixture: ComponentFixture<RequirementCreateComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RequirementCreateComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(RequirementCreateComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
